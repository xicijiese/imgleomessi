<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';

import { computed, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface RankingItem {
    rank: number;
    id: number;
    uuid: string;
    title: string;
    url: string;
    image_url: string | null;
    alt: string;
    event_date: string | null;
    category_summary: string;
    metrics: {
        hot_score: number;
        likes: number;
        favorites: number;
        comments: number;
        shares: number;
    };
}

interface RankingsPayload {
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
    filters: {
        type: string;
        window: string;
    };
    options: {
        types: Record<string, string>;
        windows: Record<string, string>;
    };
    summary: {
        total_ranked: number;
        score_label: string;
        window_label: string;
        formula: string;
    };
    items: RankingItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: { prev: string | null; next: string | null };
}

const props = defineProps<{
    rankings: RankingsPayload;
}>();

const imageFailures = ref<Record<number, boolean>>({});

const hasItems = computed(() => props.rankings.items.length > 0);

const selectType = (type: string) => {
    router.get(
        '/rankings',
        { type, window: props.rankings.filters.window },
        { preserveScroll: false, preserveState: false, replace: true },
    );
};

const selectWindow = (window: string) => {
    router.get(
        '/rankings',
        { type: props.rankings.filters.type, window },
        { preserveScroll: false, preserveState: false, replace: true },
    );
};

const metricKey = computed<keyof RankingItem['metrics']>(
    () =>
        (({
            hot: 'hot_score',
            likes: 'likes',
            favorites: 'favorites',
            comments: 'comments',
            shares: 'shares',
        })[props.rankings.filters.type] as keyof RankingItem['metrics']) ??
        'hot_score',
);
const metricLabels = {
    likes: '点赞',
    favorites: '收藏',
    comments: '评论',
    shares: '分享',
} as const;

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <SeoHead :seo="rankings.seo" />
    <main ref="pageRoot" class="archive-browser archive-community">
        <PublicHeader
            :site="rankings.site"
            :navigation="rankings.navigation"
            theme="afa"
        />
        <div class="community-main">
            <section class="intro browser-heading">
                <h1>排行榜</h1>
                <p>
                    按点赞、收藏、评论和分享记录观察图片互动热度；这里只统计可公开展示的图片。
                </p>
                <p class="summary">
                    当前 {{ rankings.summary.window_label }} ·
                    {{ rankings.summary.score_label }}
                    <strong>{{ rankings.summary.total_ranked }}</strong> 张上榜
                </p>
            </section>
            <div class="rank-toolbar browser-quick-filters">
                <div class="rank-tabs" role="group" aria-label="榜单类型">
                    <button
                        v-for="(label, value) in rankings.options.types"
                        :key="value"
                        type="button"
                        :aria-pressed="rankings.filters.type === value"
                        @click="selectType(String(value))"
                    >
                        {{ label }}
                    </button>
                </div>
                <div class="window-tabs" role="group" aria-label="时间范围">
                    <button
                        v-for="(label, value) in rankings.options.windows"
                        :key="value"
                        type="button"
                        :aria-pressed="rankings.filters.window === value"
                        @click="selectWindow(String(value))"
                    >
                        {{ label }}
                    </button>
                </div>
            </div>
            <p class="formula">
                <strong>统计口径：</strong
                >{{
                    rankings.summary.formula
                }}；评论只计算已发布普通评论，不计纠错投稿；不统计浏览量和第三方平台数据。
            </p>
            <div v-if="hasItems" class="rank-grid">
                <article
                    v-for="item in rankings.items"
                    :key="item.id"
                    class="rank-card"
                >
                    <Link
                        class="cover"
                        :href="item.url"
                        :aria-label="`查看图片：${item.title}`"
                        ><img
                            v-if="item.image_url && !imageFailures[item.id]"
                            :src="item.image_url"
                            :alt="item.alt"
                            loading="lazy"
                            @error="imageFailures[item.id] = true"
                        /><span v-else class="failed">图片暂时无法显示</span
                        ><span
                            class="rank-number"
                            :class="{ top: item.rank <= 3 }"
                            >#{{ item.rank }}</span
                        ></Link
                    >
                    <h2>
                        <Link :href="item.url">{{ item.title }}</Link>
                    </h2>
                    <div class="metric-lead">
                        <span>{{ rankings.summary.score_label }}</span
                        ><strong>{{ item.metrics[metricKey] }}</strong>
                    </div>
                    <p v-if="rankings.filters.type !== 'hot'">
                        综合热度 {{ item.metrics.hot_score }}
                    </p>
                    <div class="metrics">
                        <span v-for="(label, key) in metricLabels" :key="key"
                            >{{ label
                            }}<strong>{{ item.metrics[key] }}</strong></span
                        >
                    </div>
                </article>
            </div>
            <section v-else class="empty">
                <h2>暂无互动数据</h2>
                <p>
                    当前筛选范围内还没有点赞、收藏、评论或分享记录。产生互动后，这里会自动显示上榜图片。
                </p>
            </section>
            <ArchivePagination
                :meta="rankings.meta"
                :links="rankings.links"
                label="排行榜分页"
            />
        </div>
    </main>
</template>
<style scoped src="../../../css/community-pages.css"></style>
