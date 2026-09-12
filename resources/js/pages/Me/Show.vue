<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Bell, ExternalLink, Flag, Heart, ImageOff, MessageCircle, Save } from 'lucide-vue-next';

interface NavigationItem {
    label: string;
    url: string;
}

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
}

interface SummaryPayload {
    favorites_count: number;
    comments_count: number;
    corrections_count: number;
    reports_count: number;
    pending_count: number;
    unread_notifications_count: number;
}

interface UserPayload {
    name: string;
    email: string;
    status_label: string;
    is_banned: boolean;
    banned_until: string | null;
    ban_reason: string | null;
    created_at: string | null;
    profile_public: boolean;
    profile_bio: string | null;
    public_profile_url: string;
}

interface PhotoSummary {
    is_available: boolean;
    title: string;
    url: string | null;
    image_url: string | null;
    category_summary: string | null;
}

interface FavoriteItem {
    id: number;
    created_at: string | null;
    photo: PhotoSummary;
}

interface CommentItem {
    id: number;
    type_label: string;
    status_label: string;
    content: string;
    result_message: string;
    created_at: string | null;
    photo: PhotoSummary;
}

interface NotificationItem {
    id: string;
    title: string;
    message: string;
    url: string | null;
    is_read: boolean;
    created_at: string | null;
}

interface MePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    user: UserPayload;
    summary: SummaryPayload;
    recent_favorites: FavoriteItem[];
    recent_comments: CommentItem[];
    recent_notifications: NotificationItem[];
}

const props = defineProps<{
    me: MePayload;
}>();

const publicProfileForm = useForm({
    profile_public: props.me.user.profile_public,
    profile_bio: props.me.user.profile_bio ?? '',
});

const submitPublicProfile = () => {
    publicProfileForm.patch('/me/public-profile', {
        preserveScroll: true,
    });
};

const statItems = [
    { key: 'favorites_count', label: '收藏', icon: Heart, url: '/me/favorites' },
    { key: 'comments_count', label: '评论', icon: MessageCircle, url: '/me/comments?type=discussion' },
    { key: 'corrections_count', label: '纠错', icon: MessageCircle, url: '/me/comments?type=correction' },
    { key: 'reports_count', label: '举报', icon: Flag, url: '/me/reports' },
    { key: 'unread_notifications_count', label: '未读通知', icon: Bell, url: '/me/notifications?status=unread' },
] as const;
</script>

<template>
    <Head title="个人中心" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="me.navigation" :site="me.site" />

        <section class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Account</p>
                <h1 class="text-4xl font-semibold sm:text-5xl">个人中心</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base">
                    查看自己的收藏、评论、举报和站内通知记录。
                </p>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <UserCenterNav :user="me.user" :summary="me.summary" active="overview" />

                <div class="space-y-6">
                    <section class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-semibold">公开主页</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#5f6c7b]">
                                    开启后，游客可访问你的公开主页，只展示昵称、公开简介、公开勋章、运营守护者摘要和已发布普通评论摘要。
                                </p>
                            </div>
                            <Link
                                v-if="me.user.profile_public"
                                :href="me.user.public_profile_url"
                                class="inline-flex h-10 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                            >
                                <ExternalLink class="h-4 w-4" aria-hidden="true" />
                                查看我的公开主页
                            </Link>
                        </div>

                        <form class="mt-5 grid gap-4" @submit.prevent="submitPublicProfile">
                            <label class="flex items-start gap-3 rounded-sm border border-[#90b4ce]/30 bg-[#f7fbff] p-4 text-sm leading-6 text-[#5f6c7b]">
                                <input
                                    v-model="publicProfileForm.profile_public"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-[#90b4ce] text-[#3da9fc] focus:ring-[#3da9fc]"
                                />
                                <span>
                                    <strong class="block text-[#094067]">公开我的个人主页</strong>
                                    默认关闭；关闭后公开地址立即不可访问，不暴露用户是否存在。
                                </span>
                            </label>

                            <label class="grid gap-2 text-sm font-semibold text-[#094067]">
                                公开简介
                                <textarea
                                    v-model="publicProfileForm.profile_bio"
                                    rows="3"
                                    maxlength="240"
                                    class="rounded-sm border border-[#90b4ce]/45 bg-[#fffffe] px-3 py-2 text-sm leading-6 text-[#094067] outline-none focus:border-[#3da9fc]"
                                    placeholder="写一句会公开展示的简介，最多 240 字。"
                                />
                                <span class="text-xs font-normal text-[#5f6c7b]">不会公开邮箱、收藏、举报、通知、订单、支付记录或审核备注。</span>
                                <span v-if="publicProfileForm.errors.profile_bio" class="text-xs font-normal text-[#ef4565]">
                                    {{ publicProfileForm.errors.profile_bio }}
                                </span>
                            </label>

                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#3da9fc] px-4 text-sm font-semibold text-[#fffffe] hover:bg-[#094067] disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="publicProfileForm.processing"
                                >
                                    <Save class="h-4 w-4" aria-hidden="true" />
                                    保存公开设置
                                </button>
                                <span v-if="publicProfileForm.recentlySuccessful" class="text-sm font-semibold text-[#3da9fc]">已保存</span>
                            </div>
                        </form>
                    </section>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <Link
                            v-for="item in statItems"
                            :key="item.key"
                            :href="item.url"
                            class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm transition hover:border-[#3da9fc]"
                        >
                            <component :is="item.icon" class="h-5 w-5 text-[#3da9fc]" aria-hidden="true" />
                            <p class="mt-4 text-3xl font-semibold">{{ me.summary[item.key] }}</p>
                            <p class="mt-1 text-sm text-[#5f6c7b]">{{ item.label }}</p>
                        </Link>
                    </div>

                    <section class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="text-xl font-semibold">最近收藏</h2>
                            <Link href="/me/favorites" class="text-sm font-semibold text-[#3da9fc]">查看全部</Link>
                        </div>
                        <div v-if="me.recent_favorites.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <Link
                                v-for="favorite in me.recent_favorites"
                                :key="favorite.id"
                                :href="favorite.photo.url ?? '/me/favorites'"
                                class="overflow-hidden rounded-sm border border-[#90b4ce]/30 bg-[#fffffe]"
                            >
                                <img v-if="favorite.photo.image_url" :src="favorite.photo.image_url" :alt="favorite.photo.title" class="aspect-[4/3] w-full object-cover" />
                                <div v-else class="flex aspect-[4/3] items-center justify-center bg-[#d8eefe] text-[#5f6c7b]">
                                    <ImageOff class="h-6 w-6" aria-hidden="true" />
                                </div>
                                <div class="p-3">
                                    <p class="line-clamp-1 font-semibold">{{ favorite.photo.title }}</p>
                                    <p class="mt-1 text-xs text-[#5f6c7b]">{{ favorite.created_at }}</p>
                                </div>
                            </Link>
                        </div>
                        <p v-else class="rounded-sm bg-[#f7fbff] p-4 text-sm text-[#5f6c7b]">还没有收藏记录。</p>
                    </section>

                    <section class="grid gap-6 xl:grid-cols-2">
                        <div class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h2 class="text-xl font-semibold">最近评论 / 纠错</h2>
                                <Link href="/me/comments" class="text-sm font-semibold text-[#3da9fc]">查看全部</Link>
                            </div>
                            <div v-if="me.recent_comments.length" class="space-y-3">
                                <article v-for="comment in me.recent_comments" :key="comment.id" class="rounded-sm border border-[#90b4ce]/30 p-4">
                                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                                        <span class="rounded-full bg-[#d8eefe] px-2 py-1 text-[#094067]">{{ comment.type_label }}</span>
                                        <span class="rounded-full bg-[#f7fbff] px-2 py-1 text-[#5f6c7b]">{{ comment.status_label }}</span>
                                    </div>
                                    <p class="mt-3 line-clamp-2 text-sm leading-6">{{ comment.content }}</p>
                                    <p class="mt-2 text-xs text-[#5f6c7b]">{{ comment.result_message }}</p>
                                </article>
                            </div>
                            <p v-else class="rounded-sm bg-[#f7fbff] p-4 text-sm text-[#5f6c7b]">还没有评论或纠错记录。</p>
                        </div>

                        <div class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h2 class="text-xl font-semibold">最近通知</h2>
                                <Link href="/me/notifications" class="text-sm font-semibold text-[#3da9fc]">查看全部</Link>
                            </div>
                            <div v-if="me.recent_notifications.length" class="space-y-3">
                                <Link
                                    v-for="notice in me.recent_notifications"
                                    :key="notice.id"
                                    :href="notice.url ?? '/me/notifications'"
                                    class="block rounded-sm border border-[#90b4ce]/30 p-4 transition hover:border-[#3da9fc]"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="font-semibold">{{ notice.title }}</p>
                                        <span v-if="!notice.is_read" class="rounded-full bg-[#ef4565] px-2 py-0.5 text-xs text-[#fffffe]">未读</span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">{{ notice.message }}</p>
                                    <p class="mt-2 text-xs text-[#5f6c7b]">{{ notice.created_at }}</p>
                                </Link>
                            </div>
                            <p v-else class="rounded-sm bg-[#f7fbff] p-4 text-sm text-[#5f6c7b]">暂无通知。</p>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </main>
</template>
