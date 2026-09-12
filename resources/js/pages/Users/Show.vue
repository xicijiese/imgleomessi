<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Award, CalendarDays, HeartHandshake, ImageOff, MessageCircle, UserRound } from 'lucide-vue-next';

interface NavigationItem {
    label: string;
    url: string;
}

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
}


interface FooterPayload {
    copyright_text: string;
    icp_text: string | null;
    links: NavigationItem[];
    social_links: NavigationItem[];
}

interface BadgeItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon_key: string | null;
    color: string;
    rule_label: string;
    is_equipped: boolean;
    awarded_at: string | null;
}

interface PublicCommentItem {
    id: number;
    content: string;
    created_at: string | null;
    photo: {
        title: string;
        url: string | null;
        image_url: string | null;
    };
}

interface PublicUserProfilePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    footer: FooterPayload;
    seo: SeoPayload;
    profile: {
        user: {
            id: number;
            name: string;
            avatar: string | null;
            bio: string | null;
            joined_month: string | null;
            public_url: string;
            is_owner: boolean;
        };
        summary: {
            badges_count: number;
            public_comments_count: number;
            is_public_supporter: boolean;
        };
        supporter: {
            display_name: string;
            badge_level: string;
            badge_label: string;
            last_supported_at: string | null;
        } | null;
        equipped_badge: BadgeItem | null;
        badges: BadgeItem[];
        comments: PublicCommentItem[];
    };
}

defineProps<{
    profilePage: PublicUserProfilePayload;
}>();
</script>

<template>
    <SeoHead :seo="profilePage.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="profilePage.navigation" :site="profilePage.site" />

        <section class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-end">
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Public Profile</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-sm bg-[#3da9fc] text-2xl font-bold text-[#fffffe]">
                            <img
                                v-if="profilePage.profile.user.avatar"
                                :src="profilePage.profile.user.avatar"
                                :alt="profilePage.profile.user.name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else>{{ profilePage.profile.user.name.slice(0, 1).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0">
                            <h1 class="break-words text-4xl font-semibold sm:text-5xl">
                                {{ profilePage.profile.user.name }}
                            </h1>
                            <p class="mt-2 flex items-center gap-2 text-sm text-[#5f6c7b]">
                                <CalendarDays class="h-4 w-4" aria-hidden="true" />
                                加入于 {{ profilePage.profile.user.joined_month ?? '时间待补充' }}
                            </p>
                        </div>
                    </div>
                    <p v-if="profilePage.profile.user.bio" class="mt-5 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base">
                        {{ profilePage.profile.user.bio }}
                    </p>
                    <p v-else class="mt-5 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base">
                        这位用户公开了自己的资料页，用来展示在本站的公开参与记录。
                    </p>
                </div>

                <div class="grid gap-3 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-semibold text-[#5f6c7b]">
                            <Award class="h-4 w-4 text-[#3da9fc]" aria-hidden="true" />
                            公开勋章
                        </span>
                        <strong class="text-2xl">{{ profilePage.profile.summary.badges_count }}</strong>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-semibold text-[#5f6c7b]">
                            <MessageCircle class="h-4 w-4 text-[#3da9fc]" aria-hidden="true" />
                            公开评论
                        </span>
                        <strong class="text-2xl">{{ profilePage.profile.summary.public_comments_count }}</strong>
                    </div>
                    <Link
                        v-if="profilePage.profile.user.is_owner"
                        href="/me"
                        class="mt-2 inline-flex h-10 items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        <UserRound class="h-4 w-4" aria-hidden="true" />
                        返回个人中心
                    </Link>
                </div>
            </div>
        </section>

        <section class="px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[320px_minmax(0,1fr)]">
                <aside class="space-y-6">
                    <section v-if="profilePage.profile.equipped_badge" class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                        <p class="text-sm font-semibold text-[#5f6c7b]">当前佩戴</p>
                        <div class="mt-4 flex items-start gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-sm text-sm font-bold text-[#fffffe]" :style="{ backgroundColor: profilePage.profile.equipped_badge.color }">
                                {{ profilePage.profile.equipped_badge.icon_key ?? '奖' }}
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold">{{ profilePage.profile.equipped_badge.name }}</h2>
                                <p class="mt-1 text-sm leading-6 text-[#5f6c7b]">
                                    {{ profilePage.profile.equipped_badge.description ?? profilePage.profile.equipped_badge.rule_label }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section v-if="profilePage.profile.supporter" class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                        <p class="flex items-center gap-2 text-sm font-semibold text-[#5f6c7b]">
                            <HeartHandshake class="h-4 w-4 text-[#3da9fc]" aria-hidden="true" />
                            运营守护者身份
                        </p>
                        <h2 class="mt-3 text-xl font-semibold">{{ profilePage.profile.supporter.badge_label }}</h2>
                        <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">
                            最近支持：{{ profilePage.profile.supporter.last_supported_at ?? '时间待补充' }}
                        </p>
                    </section>

                    <section class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">已获公开勋章</h2>
                        <div v-if="profilePage.profile.badges.length" class="mt-4 grid gap-3">
                            <article v-for="badge in profilePage.profile.badges" :key="badge.id" class="rounded-sm border border-[#90b4ce]/30 p-3">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-sm text-xs font-bold text-[#fffffe]" :style="{ backgroundColor: badge.color }">
                                        {{ badge.icon_key ?? '奖' }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ badge.name }}</p>
                                        <p class="mt-1 text-xs text-[#5f6c7b]">
                                            {{ badge.rule_label }}<span v-if="badge.awarded_at"> · {{ badge.awarded_at }}</span>
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <p v-else class="mt-4 rounded-sm bg-[#f7fbff] p-4 text-sm text-[#5f6c7b]">暂无公开勋章。</p>
                    </section>
                </aside>

                <section class="min-w-0">
                    <div class="mb-5">
                        <p class="text-sm font-semibold text-[#3da9fc]">Comments</p>
                        <h2 class="mt-1 text-2xl font-semibold">公开评论摘要</h2>
                    </div>

                    <div v-if="profilePage.profile.comments.length" class="grid gap-4">
                        <article v-for="comment in profilePage.profile.comments" :key="comment.id" class="grid gap-4 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm sm:grid-cols-[112px_minmax(0,1fr)]">
                            <Link :href="comment.photo.url ?? profilePage.profile.user.public_url" class="overflow-hidden rounded-sm bg-[#d8eefe]">
                                <img v-if="comment.photo.image_url" :src="comment.photo.image_url" :alt="comment.photo.title" class="aspect-[4/3] w-full object-cover" />
                                <div v-else class="flex aspect-[4/3] items-center justify-center text-[#5f6c7b]">
                                    <ImageOff class="h-6 w-6" aria-hidden="true" />
                                </div>
                            </Link>
                            <div class="min-w-0">
                                <Link v-if="comment.photo.url" :href="comment.photo.url" class="font-semibold text-[#094067] hover:text-[#3da9fc]">
                                    {{ comment.photo.title }}
                                </Link>
                                <p v-else class="font-semibold text-[#094067]">{{ comment.photo.title }}</p>
                                <p class="mt-3 text-sm leading-7 text-[#5f6c7b]">{{ comment.content }}</p>
                                <p class="mt-3 text-xs text-[#5f6c7b]">{{ comment.created_at }}</p>
                            </div>
                        </article>
                    </div>

                    <p v-else class="rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-6 text-sm text-[#5f6c7b]">
                        暂无可公开展示的普通评论。
                    </p>
                </section>
            </div>
        </section>
    </main>
</template>
