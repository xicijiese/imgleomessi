<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { BookOpen, Heart } from 'lucide-vue-next';
import { ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface SupporterItem {
    id: number;
    avatar: string | null;
    display_name: string;
    badge_label: string;
    last_supported_at: string | null;
}

interface ContributorItem {
    id: number;
    avatar: string | null;
    display_name: string;
    bio: string | null;
    contribution_focus: string | null;
}

interface PaginatedSupporters {
    data: SupporterItem[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: {
        prev: string | null;
        next: string | null;
    };
}

interface SupportersPagePayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    footer: {
        copyright_text: string;
        icp_text: string | null;
        links: NavigationItem[];
        social_links: NavigationItem[];
    };
    summary: {
        total_supporters: number;
        total_contributors: number;
    };
    supporters: PaginatedSupporters;
    contributors: {
        data: ContributorItem[];
    };
}

defineProps<{
    supportersPage: SupportersPagePayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <SeoHead :seo="supportersPage.seo" />
    <main ref="pageRoot" class="archive-browser archive-support">
        <PublicHeader
            :site="supportersPage.site"
            :navigation="supportersPage.navigation"
            theme="afa"
        />
        <div class="support-main">
            <section class="wall-hero browser-heading">
                <div>
                    <Heart class="wall-symbol" aria-hidden="true" />
                    <h1>致谢墙</h1>
                    <p>感谢每一份支持，让影像记忆得以留存。</p>
                    <p class="wall-privacy">
                        仅展示愿意公开昵称的成员，不展示邮箱、订单号或支付明细。
                    </p>
                </div>
                <div class="wall-summary">
                    <div>
                        <strong>{{
                            supportersPage.summary.total_supporters
                        }}</strong
                        ><span>累计运营守护者</span>
                    </div>
                    <div>
                        <strong>{{
                            supportersPage.summary.total_contributors
                        }}</strong
                        ><span>档案共建者</span>
                    </div>
                </div>
            </section>
            <section class="member-section">
                <div class="section-title">
                    <div>
                        <h2>运营守护者</h2>
                        <p>帮助档案库承担服务器、存储与日常维护成本。</p>
                    </div>
                    <span class="public-count"
                        >{{
                            supportersPage.supporters.meta.total
                        }}
                        位公开展示</span
                    >
                </div>
                <div
                    v-if="supportersPage.supporters.data.length"
                    class="member-grid"
                >
                    <article
                        v-for="person in supportersPage.supporters.data"
                        :key="person.id"
                        class="member-card"
                    >
                        <div class="member-banner">
                            <Heart class="member-mark" aria-hidden="true" />
                        </div>
                        <div class="member-avatar">
                            <span class="avatar"
                                ><img
                                    v-if="
                                        person.avatar &&
                                        !imageFailures[`supporter-${person.id}`]
                                    "
                                    :src="person.avatar"
                                    :alt="person.display_name"
                                    loading="lazy"
                                    @error="
                                        imageFailures[
                                            `supporter-${person.id}`
                                        ] = true
                                    "
                                /><span v-else>{{
                                    person.display_name.slice(0, 1)
                                }}</span></span
                            >
                        </div>
                        <div class="member-copy">
                            <h3>{{ person.display_name }}</h3>
                            <span class="member-badge">{{
                                person.badge_label
                            }}</span>
                            <p class="member-date">
                                最近支持
                                <time>{{
                                    person.last_supported_at ?? '时间待补充'
                                }}</time>
                            </p>
                        </div>
                    </article>
                </div>
                <p v-else class="empty">暂无公开展示的运营守护者。</p>
                <ArchivePagination
                    :meta="supportersPage.supporters.meta"
                    :links="supportersPage.supporters.links"
                    label="致谢墙分页"
                />
            </section>
            <section class="builders-section">
                <div class="section-title">
                    <div>
                        <h2>档案共建者</h2>
                        <p>用时间、资料与考据，共同完善影像档案。</p>
                    </div>
                    <BookOpen class="builder-symbol" aria-hidden="true" />
                </div>
                <div
                    v-if="supportersPage.contributors.data.length"
                    class="builder-grid"
                >
                    <article
                        v-for="person in supportersPage.contributors.data"
                        :key="person.id"
                        class="builder-card"
                    >
                        <span class="avatar"
                            ><img
                                v-if="
                                    person.avatar &&
                                    !imageFailures[`builder-${person.id}`]
                                "
                                :src="person.avatar"
                                :alt="person.display_name"
                                loading="lazy"
                                @error="
                                    imageFailures[`builder-${person.id}`] = true
                                "
                            /><span v-else>{{
                                person.display_name.slice(0, 1)
                            }}</span></span
                        >
                        <div>
                            <span class="builder-role">档案共建者</span>
                            <h3>{{ person.display_name }}</h3>
                            <p
                                v-if="person.contribution_focus"
                                class="builder-focus"
                            >
                                {{ person.contribution_focus }}
                            </p>
                            <p v-if="person.bio">{{ person.bio }}</p>
                        </div>
                    </article>
                </div>
                <p v-else class="empty">
                    目前还没有公开展示的档案共建者。名单会在管理员确认并开启公开展示后更新。
                </p>
            </section>
        </div>
    </main>
</template>
<style scoped src="../../../css/support-pages.css"></style>
