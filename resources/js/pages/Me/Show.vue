<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);

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
    { key: 'favorites_count', label: '收藏', url: '/me/favorites' },
    {
        key: 'comments_count',
        label: '评论',
        url: '/me/comments?type=discussion',
    },
    {
        key: 'corrections_count',
        label: '纠错',
        url: '/me/comments?type=correction',
    },
    { key: 'reports_count', label: '举报', url: '/me/reports' },
    {
        key: 'unread_notifications_count',
        label: '未读通知',
        url: '/me/notifications?status=unread',
    },
] as const;
</script>

<template>
    <Head title="个人中心" />
    <div ref="root" class="me-page">
        <PublicHeader :navigation="me.navigation" :site="me.site" theme="afa" />
        <div class="me-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="overview"
                variant="archive"
            />
            <main>
                <div class="page-heading browser-heading">
                    <div>
                        <h1>个人中心</h1>
                        <p class="desktop-description">
                            从收藏继续查阅，查看最近的互动与通知。
                        </p>
                        <p class="mobile-description">
                            通过导航查看收藏、互动与账号设置。
                        </p>
                    </div>
                    <a href="#public-profile" class="secondary">公开主页设置</a>
                </div>
                <div class="quick-links">
                    <Link
                        v-for="item in statItems"
                        :key="item.key"
                        :href="item.url"
                        class="stat-link"
                    >
                        <span class="stat-label">{{ item.label }}</span
                        ><strong>{{ me.summary[item.key] }}</strong
                        ><span class="stat-action"
                            >查看{{
                                item.label === '未读通知' ? '未读' : item.label
                            }}</span
                        >
                    </Link>
                </div>
                <div class="overview-grid">
                    <div class="primary-column">
                        <section class="favorites panel">
                            <div class="section-heading">
                                <h2>最近收藏</h2>
                                <Link class="secondary" href="/me/favorites"
                                    >全部收藏</Link
                                >
                            </div>
                            <div
                                v-if="me.recent_favorites.length"
                                class="photo-grid"
                            >
                                <Link
                                    v-for="favorite in me.recent_favorites"
                                    :key="favorite.id"
                                    :href="
                                        favorite.photo.url ?? '/me/favorites'
                                    "
                                    class="photo-card"
                                >
                                    <div class="photo-media">
                                        <img
                                            v-if="favorite.photo.image_url"
                                            :src="favorite.photo.image_url"
                                            :alt="favorite.photo.title"
                                            loading="lazy"
                                        /><span v-else>图片已不可用</span>
                                    </div>
                                    <h3 :title="favorite.photo.title">
                                        {{ favorite.photo.title }}
                                    </h3>
                                    <p>收藏于 {{ favorite.created_at }}</p>
                                </Link>
                            </div>
                            <div v-else class="empty">
                                <p>还没有收藏记录。</p>
                                <Link class="secondary" href="/photos"
                                    >浏览图库</Link
                                >
                            </div>
                        </section>
                        <section class="comments panel">
                            <div class="section-heading">
                                <h2>最近评论 / 纠错</h2>
                                <Link class="secondary" href="/me/comments"
                                    >全部记录</Link
                                >
                            </div>
                            <div v-if="me.recent_comments.length">
                                <article
                                    v-for="comment in me.recent_comments"
                                    :key="comment.id"
                                    class="comment"
                                >
                                    <div class="comment-tags">
                                        <span>{{ comment.type_label }}</span
                                        ><span
                                            :class="{
                                                pending:
                                                    comment.status_label ===
                                                    '待审核',
                                            }"
                                            >{{ comment.status_label }}</span
                                        >
                                    </div>
                                    <p>{{ comment.content }}</p>
                                    <small>{{ comment.result_message }}</small>
                                </article>
                            </div>
                            <div v-else class="empty">
                                还没有评论或纠错记录。
                            </div>
                        </section>
                    </div>
                    <div class="secondary-column">
                        <section class="notifications panel">
                            <div class="section-heading">
                                <h2>最近通知</h2>
                                <Link class="secondary" href="/me/notifications"
                                    >全部通知</Link
                                >
                            </div>
                            <div v-if="me.recent_notifications.length">
                                <Link
                                    v-for="notice in me.recent_notifications"
                                    :key="notice.id"
                                    class="notice"
                                    :href="notice.url ?? '/me/notifications'"
                                >
                                    <h3>
                                        <span>{{ notice.title }}</span
                                        ><span
                                            v-if="!notice.is_read"
                                            class="unread"
                                            >未读</span
                                        >
                                    </h3>
                                    <p>{{ notice.message }}</p>
                                    <time>{{ notice.created_at }}</time>
                                </Link>
                            </div>
                            <div v-else class="empty">暂无通知。</div>
                        </section>
                        <section id="public-profile" class="profile panel">
                            <div class="section-heading"><h2>公开主页</h2></div>
                            <p class="body-copy">
                                开启后，游客可访问你的公开主页，只展示昵称、公开简介、公开勋章、运营守护者摘要和已发布普通评论摘要。
                            </p>
                            <Link
                                v-if="me.user.profile_public"
                                class="secondary profile-view"
                                :href="me.user.public_profile_url"
                                >查看我的公开主页</Link
                            >
                            <form
                                class="profile-form"
                                @submit.prevent="submitPublicProfile"
                            >
                                <label class="privacy-switch">
                                    <span
                                        ><strong>公开我的个人主页</strong
                                        ><small
                                            >默认关闭；关闭后公开地址立即不可访问，不暴露用户是否存在。</small
                                        ></span
                                    >
                                    <input
                                        v-model="
                                            publicProfileForm.profile_public
                                        "
                                        type="checkbox"
                                        :aria-invalid="
                                            Boolean(
                                                publicProfileForm.errors
                                                    .profile_public,
                                            )
                                        "
                                        :aria-describedby="
                                            publicProfileForm.errors
                                                .profile_public
                                                ? 'profile-public-error'
                                                : undefined
                                        "
                                    />
                                </label>
                                <p
                                    v-if="
                                        publicProfileForm.errors.profile_public
                                    "
                                    id="profile-public-error"
                                    class="error"
                                    role="alert"
                                >
                                    {{
                                        publicProfileForm.errors.profile_public
                                    }}
                                </p>
                                <label class="field-label" for="profile-bio"
                                    >公开简介</label
                                >
                                <textarea
                                    id="profile-bio"
                                    v-model="publicProfileForm.profile_bio"
                                    rows="4"
                                    maxlength="240"
                                    :aria-invalid="
                                        Boolean(
                                            publicProfileForm.errors
                                                .profile_bio,
                                        )
                                    "
                                    :aria-describedby="
                                        publicProfileForm.errors.profile_bio
                                            ? 'privacy-help profile-error'
                                            : 'privacy-help'
                                    "
                                    placeholder="写一句会公开展示的简介，最多 240 字。"
                                />
                                <p id="privacy-help" class="help">
                                    不会公开邮箱、收藏、举报、通知、订单、支付记录或审核备注。
                                </p>
                                <p
                                    v-if="publicProfileForm.errors.profile_bio"
                                    id="profile-error"
                                    class="error"
                                    role="alert"
                                >
                                    {{ publicProfileForm.errors.profile_bio }}
                                </p>
                                <button
                                    class="primary"
                                    type="submit"
                                    :disabled="publicProfileForm.processing"
                                    :aria-busy="publicProfileForm.processing"
                                >
                                    {{
                                        publicProfileForm.processing
                                            ? '正在保存…'
                                            : '保存公开设置'
                                    }}
                                </button>
                                <p
                                    v-if="publicProfileForm.recentlySuccessful"
                                    class="help"
                                    role="status"
                                >
                                    已保存
                                </p>
                            </form>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped src="./Show.css"></style>
