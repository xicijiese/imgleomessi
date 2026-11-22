<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { useGalleryScrollReveal } from '@/composables/useGalleryScrollReveal';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';

import { computed, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
}

interface MonthSummary {
    month: number;
    label: string;
    url: string;
    photos_count: number;
}

interface YearSummary {
    year: number;
    url: string;
    photos_count: number;
    albums_count: number;
    cover_image_url: string | null;
    cover_alt: string;
    months: MonthSummary[];
}

interface TimelineIndexPayload {
    site: SitePayload;
    navigation: NavigationItem[];
    seo: SeoPayload;
    scope: {
        type: 'index';
        title: string;
        subtitle: string;
    };
    summary: {
        years_count: number;
        photos_count: number;
        albums_count: number;
    };
    years: YearSummary[];
}

const props = defineProps<{
    timeline: TimelineIndexPayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});
const hasYears = computed(() => props.timeline.years.length > 0);

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useGalleryScrollReveal(
    pageRoot,
    () => props.timeline,
    '.year-entry, .section-heading, .photo-grid > *',
);
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <SeoHead :seo="timeline.seo" />
    <main ref="pageRoot" class="archive-browser archive-timeline">
        <PublicHeader
            :site="timeline.site"
            :navigation="timeline.navigation"
            theme="afa"
        />
        <div class="timeline-main">
            <section class="intro browser-heading">
                <h1>{{ timeline.scope.title }}</h1>
                <p>{{ timeline.scope.subtitle }}</p>
                <div class="summary">
                    <span
                        ><strong>{{ timeline.summary.years_count }}</strong>
                        个年份</span
                    ><span
                        ><strong>{{ timeline.summary.photos_count }}</strong>
                        张图片</span
                    ><span
                        ><strong>{{ timeline.summary.albums_count }}</strong>
                        个相册</span
                    >
                </div>
            </section>
            <nav
                v-if="hasYears"
                class="year-links"
                aria-label="年份快捷入口"
            >
                <Link
                    v-for="year in timeline.years"
                    :key="year.year"
                    :href="year.url"
                    >{{ year.year }}</Link
                >
            </nav>
            <div v-if="hasYears" class="year-list">
                <article
                    v-for="year in timeline.years"
                    :key="year.year"
                    class="year-entry"
                >
                    <Link
                        :href="year.url"
                        class="cover"
                        :aria-label="`浏览 ${year.year} 年时间线`"
                        ><img
                            v-if="
                                year.cover_image_url &&
                                !imageFailures[`year-${year.year}`]
                            "
                            :src="year.cover_image_url"
                            :alt="year.cover_alt"
                            loading="lazy"
                            @error="imageFailures[`year-${year.year}`] = true"
                        /><span v-else class="failed"
                            >封面暂时无法显示</span
                        ></Link
                    >
                    <div class="year-copy">
                        <h2>{{ year.year }} 年</h2>
                        <p>
                            {{ year.photos_count }} 张公开图片 ·
                            {{ year.albums_count }} 个相关相册
                        </p>
                        <nav
                            class="months"
                            :aria-label="`${year.year} 年月份入口`"
                        >
                            <Link
                                v-for="month in year.months.slice(0, 8)"
                                :key="month.month"
                                :href="month.url"
                                >{{ month.label }}
                                <small>{{ month.photos_count }} 张</small></Link
                            >
                        </nav>
                        <Link :href="year.url"
                            >进入 {{ year.year }} 年
                            <span aria-hidden="true">→</span></Link
                        >
                    </div>
                </article>
            </div>
            <section v-else class="empty">
                <h2>暂无可浏览的时间线</h2>
                <p>时间线只展示已发布且已标注事件日期的公开图片。</p>
            </section>
        </div>
    </main>
</template>
<style scoped src="./Timeline.css"></style>
