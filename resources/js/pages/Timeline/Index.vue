<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, ImageOff } from 'lucide-vue-next';
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

interface TagPayload {
    id: number;
    name: string;
    type: string;
}

interface PhotoCard {
    id: number;
    uuid: string;
    title: string;
    description: string;
    url: string;
    image_url: string | null;
    alt: string;
    event_date: string | null;
    category_summary: string;
    tags: TagPayload[];
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
    latest_photos: PhotoCard[];
}

const props = defineProps<{
    timeline: TimelineIndexPayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});
const hasYears = computed(() => props.timeline.years.length > 0);
const topYears = computed(() => props.timeline.years.slice(0, 12));
const formatDate = (date: string | null) => date ?? '日期待补充';
</script>

<template>
    <SeoHead :seo="timeline.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="timeline.navigation" :site="timeline.site" />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Timeline
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">
                        {{ timeline.scope.title }}
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        {{ timeline.scope.subtitle }}
                    </p>
                </div>

                <div
                    class="grid gap-2 rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm sm:grid-cols-3 sm:gap-5"
                >
                    <span
                        ><strong class="text-[#094067]">{{
                            timeline.summary.years_count
                        }}</strong>
                        年</span
                    >
                    <span
                        ><strong class="text-[#094067]">{{
                            timeline.summary.photos_count
                        }}</strong>
                        张图片</span
                    >
                    <span
                        ><strong class="text-[#094067]">{{
                            timeline.summary.albums_count
                        }}</strong>
                        个相册</span
                    >
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <nav
                    v-if="topYears.length"
                    class="mb-8 flex flex-wrap gap-2"
                    aria-label="年份快捷入口"
                >
                    <Link
                        v-for="year in topYears"
                        :key="year.year"
                        :href="year.url"
                        class="inline-flex h-10 items-center rounded-sm border border-[#90b4ce]/60 bg-[#fffffe] px-3 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        {{ year.year }}
                    </Link>
                </nav>

                <div v-if="hasYears" class="grid gap-5 lg:grid-cols-2">
                    <article
                        v-for="year in timeline.years"
                        :key="year.year"
                        class="overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm"
                    >
                        <div class="grid sm:grid-cols-[220px_minmax(0,1fr)]">
                            <Link
                                :href="year.url"
                                class="group block aspect-[4/3] overflow-hidden bg-[#d8eefe] sm:aspect-auto sm:h-full"
                                :aria-label="`浏览 ${year.year} 年时间线`"
                            >
                                <img
                                    v-if="
                                        year.cover_image_url &&
                                        !imageFailures[`year-${year.year}`]
                                    "
                                    :src="year.cover_image_url"
                                    :alt="year.cover_alt"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                    @error="
                                        imageFailures[`year-${year.year}`] =
                                            true
                                    "
                                />
                                <div
                                    v-else
                                    class="flex h-full min-h-40 w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                                >
                                    <CalendarDays
                                        class="h-9 w-9"
                                        aria-hidden="true"
                                    />
                                </div>
                            </Link>

                            <div class="grid gap-5 p-5">
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <h2
                                            class="text-2xl font-semibold text-[#094067]"
                                        >
                                            {{ year.year }} 年
                                        </h2>
                                        <p class="mt-2 text-sm text-[#5f6c7b]">
                                            {{ year.photos_count }} 张公开图片 ·
                                            {{ year.albums_count }} 个相关相册
                                        </p>
                                    </div>
                                    <Link
                                        :href="year.url"
                                        class="inline-flex h-10 shrink-0 items-center gap-1 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                                        :aria-label="`进入 ${year.year} 年`"
                                    >
                                        进入
                                        <ChevronRight
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        v-for="month in year.months.slice(0, 8)"
                                        :key="`${year.year}-${month.month}`"
                                        :href="month.url"
                                        class="rounded-full bg-[#d8eefe] px-3 py-1.5 text-xs font-semibold text-[#094067] hover:bg-[#3da9fc] hover:text-[#fffffe]"
                                    >
                                        {{ month.label }} ·
                                        {{ month.photos_count }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-sm bg-[#d8eefe] px-6 py-14 text-center"
                >
                    <h2 class="text-2xl font-semibold text-[#094067]">
                        暂无可浏览的时间线
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]"
                    >
                        只有已发布、可公开展示并且填写了事件日期的图片会进入时间线。
                    </p>
                </div>

                <section v-if="timeline.latest_photos.length" class="mt-12">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <h2 class="text-2xl font-semibold text-[#094067]">
                            最近时间节点
                        </h2>
                        <Link
                            href="/photos?sort=event_desc"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-[#3da9fc]"
                        >
                            查看图库
                            <ChevronRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                    </div>

                    <div
                        class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <Link
                            v-for="photo in timeline.latest_photos"
                            :key="photo.uuid"
                            :href="photo.url"
                            class="group overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                            :aria-label="`查看图片：${photo.title}`"
                        >
                            <div
                                class="aspect-[4/3] overflow-hidden bg-[#d8eefe]"
                            >
                                <img
                                    v-if="
                                        photo.image_url &&
                                        !imageFailures[`photo-${photo.id}`]
                                    "
                                    :src="photo.image_url"
                                    :alt="photo.alt"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                    @error="
                                        imageFailures[`photo-${photo.id}`] =
                                            true
                                    "
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                                >
                                    <ImageOff
                                        class="h-8 w-8"
                                        aria-hidden="true"
                                    />
                                </div>
                            </div>
                            <div class="grid min-h-32 gap-3 p-4">
                                <div>
                                    <h3
                                        class="line-clamp-2 text-base leading-6 font-semibold text-[#094067]"
                                    >
                                        {{ photo.title }}
                                    </h3>
                                    <p class="mt-2 text-sm text-[#5f6c7b]">
                                        {{ formatDate(photo.event_date) }}
                                    </p>
                                </div>
                                <p
                                    v-if="photo.category_summary"
                                    class="truncate text-sm text-[#5f6c7b]"
                                >
                                    {{ photo.category_summary }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </section>
    </main>
</template>
