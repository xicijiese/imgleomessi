<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import {
    Bookmark,
    Flame,
    Heart,
    ImageOff,
    MessageCircle,
    Share2,
    Trophy,
} from 'lucide-vue-next';
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

const metricLabel = (key: keyof RankingItem['metrics']) =>
    ({
        hot_score: '热度',
        likes: '点赞',
        favorites: '收藏',
        comments: '评论',
        shares: '分享',
    })[key];

const formatDate = (date: string | null) => date ?? '日期待补充';
</script>

<template>
    <SeoHead :seo="rankings.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="rankings.navigation" :site="rankings.site" />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Rankings
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">排行榜</h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        按点赞、收藏、评论和分享记录观察图片互动热度；这里只统计可公开展示的图片。
                    </p>
                </div>

                <div
                    class="rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm"
                >
                    当前 {{ rankings.summary.window_label }} ·
                    {{ rankings.summary.score_label }}
                    <span class="ml-2 font-semibold text-[#094067]">{{
                        rankings.summary.total_ranked
                    }}</span>
                    张上榜
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex flex-wrap gap-2" aria-label="榜单类型">
                        <button
                            v-for="(label, value) in rankings.options.types"
                            :key="value"
                            type="button"
                            class="rounded-sm border px-4 py-2 text-sm font-semibold transition"
                            :class="
                                rankings.filters.type === value
                                    ? 'border-[#3da9fc] bg-[#3da9fc] text-[#fffffe]'
                                    : 'border-[#90b4ce]/50 bg-[#fffffe] text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]'
                            "
                            @click="selectType(String(value))"
                        >
                            {{ label }}
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2" aria-label="时间范围">
                        <button
                            v-for="(label, value) in rankings.options.windows"
                            :key="value"
                            type="button"
                            class="rounded-sm border px-4 py-2 text-sm font-semibold transition"
                            :class="
                                rankings.filters.window === value
                                    ? 'border-[#094067] bg-[#094067] text-[#fffffe]'
                                    : 'border-[#90b4ce]/50 bg-[#fffffe] text-[#094067] hover:border-[#094067]'
                            "
                            @click="selectWindow(String(value))"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <div
                    class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] px-4 py-3 text-sm leading-6 text-[#5f6c7b]"
                >
                    <span class="font-semibold text-[#094067]">统计口径：</span>
                    {{
                        rankings.summary.formula
                    }}；评论只计算已发布普通评论，不计纠错投稿；不统计浏览量和第三方平台数据。
                </div>

                <div
                    v-if="hasItems"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Link
                        v-for="item in rankings.items"
                        :key="item.id"
                        :href="item.url"
                        class="group overflow-hidden rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] shadow-sm transition hover:-translate-y-0.5 hover:border-[#3da9fc] hover:shadow-md"
                    >
                        <div class="relative aspect-[4/3] bg-[#d8eefe]">
                            <img
                                v-if="item.image_url && !imageFailures[item.id]"
                                :src="item.image_url"
                                :alt="item.alt"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                loading="lazy"
                                @error="imageFailures[item.id] = true"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-[#5f6c7b]"
                            >
                                <ImageOff class="h-8 w-8" aria-hidden="true" />
                            </div>
                            <div
                                class="absolute top-3 left-3 flex h-10 min-w-10 items-center justify-center rounded-sm bg-[#094067] px-3 text-sm font-bold text-[#fffffe]"
                            >
                                #{{ item.rank }}
                            </div>
                            <div
                                class="absolute right-3 bottom-3 flex items-center gap-1 rounded-sm bg-[#fffffe]/90 px-3 py-1 text-sm font-bold text-[#094067] shadow-sm"
                            >
                                <Flame
                                    class="h-4 w-4 text-[#ef4565]"
                                    aria-hidden="true"
                                />
                                {{ item.metrics.hot_score }}
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <div>
                                <h2
                                    class="line-clamp-2 min-h-12 text-base leading-6 font-semibold text-[#094067]"
                                >
                                    {{ item.title }}
                                </h2>
                                <p class="mt-2 text-xs text-[#5f6c7b]">
                                    {{ formatDate(item.event_date) }}
                                </p>
                                <p
                                    class="mt-1 line-clamp-1 text-xs text-[#5f6c7b]"
                                >
                                    {{ item.category_summary }}
                                </p>
                            </div>

                            <div
                                class="grid grid-cols-4 gap-2 text-xs text-[#5f6c7b]"
                            >
                                <span
                                    class="flex items-center gap-1"
                                    :title="metricLabel('likes')"
                                >
                                    <Heart class="h-4 w-4" aria-hidden="true" />
                                    {{ item.metrics.likes }}
                                </span>
                                <span
                                    class="flex items-center gap-1"
                                    :title="metricLabel('favorites')"
                                >
                                    <Bookmark
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    {{ item.metrics.favorites }}
                                </span>
                                <span
                                    class="flex items-center gap-1"
                                    :title="metricLabel('comments')"
                                >
                                    <MessageCircle
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    {{ item.metrics.comments }}
                                </span>
                                <span
                                    class="flex items-center gap-1"
                                    :title="metricLabel('shares')"
                                >
                                    <Share2
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    {{ item.metrics.shares }}
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="flex min-h-80 flex-col items-center justify-center rounded-sm border border-dashed border-[#90b4ce]/60 bg-[#fffffe] px-6 text-center"
                >
                    <Trophy
                        class="h-10 w-10 text-[#3da9fc]"
                        aria-hidden="true"
                    />
                    <h2 class="mt-4 text-xl font-semibold">暂无互动数据</h2>
                    <p class="mt-3 max-w-md text-sm leading-6 text-[#5f6c7b]">
                        当前筛选范围内还没有点赞、收藏、评论或分享记录。产生互动后，这里会自动显示上榜图片。
                    </p>
                </div>
            </div>
        </section>

        <PublicFooter :footer="rankings.footer" :site="rankings.site" />
    </main>
</template>
