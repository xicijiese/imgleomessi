<script setup lang="ts">
import BadgeIcon from '@/components/BadgeIcon.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';

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
    image_url: string | null;
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
        equipped_badges: BadgeItem[];
        badges: BadgeItem[];
        comments: PublicCommentItem[];
    };
}

const props = defineProps<{
    profilePage: PublicUserProfilePayload;
}>();

import { ref, watch } from 'vue';
const imageFailures = ref<Record<string, boolean>>({});
watch(
    () => props.profilePage.profile.user.id,
    () => {
        imageFailures.value = {};
    },
);

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <SeoHead :seo="profilePage.seo" />
    <main ref="pageRoot" class="archive-browser archive-community">
        <PublicHeader
            :site="profilePage.site"
            :navigation="profilePage.navigation"
            theme="afa"
        />
        <div class="community-main">
            <section class="intro browser-heading">
                <div class="profile-heading">
                    <div class="avatar">
                        <img
                            v-if="
                                profilePage.profile.user.avatar &&
                                !imageFailures.avatar
                            "
                            :src="profilePage.profile.user.avatar"
                            :alt="profilePage.profile.user.name"
                            @error="imageFailures.avatar = true"
                        /><span v-else>{{
                            profilePage.profile.user.name
                                .slice(0, 1)
                                .toUpperCase()
                        }}</span>
                    </div>
                    <div>
                        <h1>{{ profilePage.profile.user.name }}</h1>
                        <p>
                            加入于
                            {{
                                profilePage.profile.user.joined_month ??
                                '时间待补充'
                            }}
                        </p>
                    </div>
                </div>
                <p class="profile-bio">
                    {{
                        profilePage.profile.user.bio ||
                        '这位用户公开了自己的资料页，用来展示在本站的公开参与记录。'
                    }}
                </p>
                <div class="profile-stat">
                    <span
                        ><strong>{{
                            profilePage.profile.summary.badges_count
                        }}</strong>
                        枚公开勋章</span
                    ><span
                        ><strong>{{
                            profilePage.profile.summary.public_comments_count
                        }}</strong>
                        条公开评论</span
                    ><Link
                        v-if="profilePage.profile.user.is_owner"
                        class="owner-link"
                        href="/me"
                        >返回个人中心</Link
                    >
                </div>
            </section>
            <div class="profile-body">
                <aside class="profile-side">
                    <section v-if="profilePage.profile.equipped_badges.length">
                        <h2>当前佩戴</h2>
                        <div
                            v-for="badge in profilePage.profile.equipped_badges"
                            :key="badge.id"
                            class="badge-line"
                        >
                            <BadgeIcon :badge="badge" /><strong>{{
                                badge.name
                            }}</strong>
                        </div>
                    </section>
                    <section
                        v-if="profilePage.profile.supporter"
                        class="supporter"
                    >
                        <p>运营守护者身份</p>
                        <h2>{{ profilePage.profile.supporter.badge_label }}</h2>
                        <p>
                            最近支持：{{
                                profilePage.profile.supporter
                                    .last_supported_at ?? '时间待补充'
                            }}
                        </p>
                    </section>
                    <section>
                        <h2>已获公开勋章</h2>
                        <Link href="/me/badges/hall" class="hall-link"
                            >浏览勋章大厅</Link
                        ><template v-if="profilePage.profile.badges.length"
                            ><article
                                v-for="badge in profilePage.profile.badges"
                                :key="badge.id"
                                class="badge-line"
                            >
                                <BadgeIcon :badge="badge" />
                                <div>
                                    <strong>{{ badge.name }}</strong>
                                    <p>
                                        {{ badge.rule_label
                                        }}<span v-if="badge.awarded_at">
                                            · {{ badge.awarded_at }}</span
                                        >
                                    </p>
                                </div>
                            </article></template
                        >
                        <p v-else class="quiet-empty">暂无公开勋章。</p>
                    </section>
                </aside>
                <section class="profile-comments">
                    <h2>公开评论摘要</h2>
                    <template v-if="profilePage.profile.comments.length"
                        ><article
                            v-for="comment in profilePage.profile.comments"
                            :key="comment.id"
                            class="comment"
                        >
                            <Link
                                class="cover"
                                :href="
                                    comment.photo.url ??
                                    profilePage.profile.user.public_url
                                "
                                ><img
                                    v-if="
                                        comment.photo.image_url &&
                                        !imageFailures[`comment-${comment.id}`]
                                    "
                                    :src="comment.photo.image_url"
                                    :alt="comment.photo.title"
                                    loading="lazy"
                                    @error="
                                        imageFailures[`comment-${comment.id}`] =
                                            true
                                    "
                                /><span v-else class="failed"
                                    >图片暂时无法显示</span
                                ></Link
                            >
                            <div>
                                <h3>
                                    <Link
                                        v-if="comment.photo.url"
                                        :href="comment.photo.url"
                                        >{{ comment.photo.title }}</Link
                                    ><span v-else>{{
                                        comment.photo.title
                                    }}</span>
                                </h3>
                                <p>{{ comment.content }}</p>
                                <time>{{ comment.created_at }}</time>
                            </div>
                        </article></template
                    >
                    <p v-else class="quiet-empty">暂无可公开展示的普通评论。</p>
                </section>
            </div>
        </div>
    </main>
</template>
<style scoped src="../../../css/community-pages.css"></style>
