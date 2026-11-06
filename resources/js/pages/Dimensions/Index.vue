<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';

import { computed, reactive, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface DimensionCard {
    id: number;
    name: string;
    slug: string;
    description: string;
    url: string;
    gallery_url: string;
    cover_image_url: string | null;
    cover_alt: string;
    public_photos_count: number;
    public_albums_count: number;
    latest_event_date: string | null;
}

interface ArchiveIndexPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    dimension: {
        type: 'teams' | 'seasons';
        label: string;
        eyebrow: string;
        title: string;
        subtitle: string;
        path: string;
        empty_title: string;
        empty_description: string;
    };
    summary: {
        dimensions_count: number;
        photos_count: number;
        albums_count: number;
    };
    filters: {
        q: string;
    };
    dimensions: DimensionCard[];
}

const props = defineProps<{
    archive: ArchiveIndexPayload;
}>();

const imageFailures = ref<Record<number, boolean>>({});
const form = reactive({
    q: props.archive.filters.q,
});
const hasDimensions = computed(() => props.archive.dimensions.length > 0);
const formatDate = (date: string | null) => date ?? '日期待补充';

const applyFilters = () => {
    router.get(
        props.archive.dimension.path,
        { q: form.q.trim() || undefined },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    form.q = '';
    router.get(
        props.archive.dimension.path,
        {},
        { preserveScroll: true, replace: true },
    );
};

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <SeoHead :seo="archive.seo" />
    <main ref="pageRoot" class="archive-browser archive-dimensions">
        <PublicHeader
            :site="archive.site"
            :navigation="archive.navigation"
            theme="afa"
        />
        <div class="dimension-main">
            <section class="intro browser-heading">
                <h1>{{ archive.dimension.title }}</h1>
                <p>{{ archive.dimension.subtitle }}</p>
                <div class="summary">
                    <span
                        ><strong>{{ archive.summary.dimensions_count }}</strong>
                        个{{ archive.dimension.label }}</span
                    ><span
                        ><strong>{{ archive.summary.photos_count }}</strong>
                        张公开图片</span
                    ><span
                        ><strong>{{ archive.summary.albums_count }}</strong>
                        个相册</span
                    >
                </div>
            </section>
            <form
                class="controls browser-quick-filters"
                @submit.prevent="applyFilters"
            >
                <label class="keyword"
                    >关键词<input
                        v-model="form.q"
                        type="search"
                        placeholder="标题、说明或 URL 标识" /></label
                ><button class="primary" type="submit">搜索</button
                ><button class="outline" type="button" @click="resetFilters">
                    重置
                </button>
            </form>
            <p class="result-line">
                共 {{ archive.dimensions.length }} 个{{
                    archive.dimension.label
                }}
            </p>
            <div v-if="hasDimensions" class="dimension-grid">
                <article
                    v-for="item in archive.dimensions"
                    :key="item.id"
                    class="dimension-card"
                >
                    <Link
                        :href="item.url"
                        class="cover"
                        :aria-label="`进入${item.name}`"
                        ><img
                            v-if="
                                item.cover_image_url && !imageFailures[item.id]
                            "
                            :src="item.cover_image_url"
                            :alt="item.cover_alt"
                            loading="lazy"
                            @error="imageFailures[item.id] = true"
                        /><span v-else class="failed"
                            >图片暂时无法显示</span
                        ></Link
                    >
                    <div class="card-copy">
                        <h2>
                            <Link :href="item.url">{{ item.name }}</Link>
                        </h2>
                        <p v-if="item.description">{{ item.description }}</p>
                        <p>
                            {{ item.public_photos_count }} 张公开图片 ·
                            {{ item.public_albums_count }} 个相册
                        </p>
                        <p>
                            最近事件：{{ formatDate(item.latest_event_date) }}
                        </p>
                        <div class="card-actions">
                            <Link :href="item.url">进入档案</Link
                            ><Link :href="item.gallery_url">返回图库</Link>
                        </div>
                    </div>
                </article>
            </div>
            <section v-else class="empty">
                <h2>{{ archive.dimension.empty_title }}</h2>
                <p>{{ archive.dimension.empty_description }}</p>
            </section>
        </div>
    </main>
</template>
<style scoped src="./Dimensions.css"></style>
