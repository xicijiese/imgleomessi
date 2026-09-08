<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ImageOff,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
}

interface SelectOption {
    value: string;
    label: string;
}

interface CategoryOption {
    id: number;
    name: string;
}

interface CategoryGroup {
    id: number;
    name: string;
    slug: string;
    children: CategoryOption[];
}

interface TagPayload {
    id: number;
    name: string;
    type: string;
}

interface MonthSummary {
    month: number;
    label: string;
    url: string;
    photos_count: number;
    cover_image_url: string | null;
    cover_alt: string;
}

interface BreadcrumbItem {
    label: string;
    url: string;
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
    albums: NavigationItem[];
}

interface AlbumCard {
    id: number;
    title: string;
    slug: string;
    description: string;
    url: string;
    cover_image_url: string | null;
    cover_alt: string;
    public_photos_count: number;
    category_summary: string;
}

interface TopicCard {
    title: string;
    url: string;
    description: string;
}

interface TimelineArchivePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    seo: SeoPayload;
    scope: {
        type: 'year' | 'month';
        title: string;
        subtitle: string;
        year: number;
        month: number | null;
        month_label?: string;
        breadcrumbs: BreadcrumbItem[];
    };
    summary: {
        photos_count: number;
        albums_count: number;
        months_count: number;
    };
    filters: {
        q: string;
        categories: Record<string, number>;
        tags: number[];
        sort: string;
    };
    filter_options: {
        category_groups: CategoryGroup[];
        tags: TagPayload[];
        sorts: SelectOption[];
    };
    months: MonthSummary[];
    photos: {
        data: PhotoCard[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
        links: {
            prev: string | null;
            next: string | null;
        };
    };
    albums: AlbumCard[];
    related_topics: TopicCard[];
}

const props = defineProps<{
    timeline: TimelineArchivePayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});
const form = reactive({
    q: props.timeline.filters.q,
    sort: props.timeline.filters.sort,
    categories: Object.fromEntries(
        props.timeline.filter_options.category_groups.map((group) => [
            group.slug,
            props.timeline.filters.categories[group.slug]?.toString() ?? '',
        ]),
    ) as Record<string, string>,
    tags: props.timeline.filters.tags.map((id) => id.toString()),
});

const submitPath = computed(() => {
    const year = props.timeline.scope.year;
    const month = props.timeline.scope.month;

    return month === null
        ? `/timeline/${year}`
        : `/timeline/${year}/${month.toString().padStart(2, '0')}`;
});
const hasPhotos = computed(() => props.timeline.photos.data.length > 0);
const visibleMonths = computed(() => props.timeline.months.slice(0, 12));
const formatDate = (date: string | null) => date ?? '日期待补充';

const selectedQuery = () => {
    const categories = Object.fromEntries(
        Object.entries(form.categories).filter((entry) => entry[1] !== ''),
    );

    return {
        q: form.q.trim() || undefined,
        sort: form.sort === 'event_desc' ? undefined : form.sort,
        categories: Object.keys(categories).length ? categories : undefined,
        tags: form.tags.length ? form.tags : undefined,
    };
};

const applyFilters = () => {
    router.get(submitPath.value, selectedQuery(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    form.q = '';
    form.sort = 'event_desc';
    Object.keys(form.categories).forEach((key) => {
        form.categories[key] = '';
    });
    form.tags = [];
    router.get(submitPath.value, {}, { preserveScroll: true, replace: true });
};
</script>

<template>
    <SeoHead :seo="timeline.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="timeline.navigation" :site="timeline.site" />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <nav
                    class="mb-5 flex flex-wrap gap-2 text-sm text-[#5f6c7b]"
                    aria-label="面包屑"
                >
                    <template
                        v-for="(item, index) in timeline.scope.breadcrumbs"
                        :key="item.url"
                    >
                        <Link
                            :href="item.url"
                            class="font-semibold text-[#094067] hover:text-[#3da9fc]"
                            >{{ item.label }}</Link
                        >
                        <span
                            v-if="index < timeline.scope.breadcrumbs.length - 1"
                            >/</span
                        >
                    </template>
                </nav>

                <div
                    class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
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
                        <span
                            ><strong class="text-[#094067]">{{
                                timeline.summary.months_count
                            }}</strong>
                            个月份</span
                        >
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[280px_minmax(0,1fr)]"
            >
                <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                    <form
                        class="rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] p-4 shadow-sm"
                        @submit.prevent="applyFilters"
                    >
                        <div class="space-y-4">
                            <label class="block">
                                <span
                                    class="mb-1 block text-sm font-semibold text-[#094067]"
                                    >关键词</span
                                >
                                <span
                                    class="flex h-11 items-center rounded-sm border border-[#90b4ce]/60 px-3"
                                >
                                    <Search
                                        class="mr-2 h-4 w-4 shrink-0 text-[#5f6c7b]"
                                        aria-hidden="true"
                                    />
                                    <input
                                        v-model="form.q"
                                        type="search"
                                        class="w-full bg-transparent text-sm outline-none placeholder:text-[#5f6c7b]"
                                        placeholder="标题或描述"
                                    />
                                </span>
                            </label>

                            <label class="block">
                                <span
                                    class="mb-1 block text-sm font-semibold text-[#094067]"
                                    >排序</span
                                >
                                <select
                                    v-model="form.sort"
                                    class="h-11 w-full rounded-sm border border-[#90b4ce]/60 bg-[#fffffe] px-3 text-sm outline-none focus:border-[#3da9fc]"
                                >
                                    <option
                                        v-for="sort in timeline.filter_options
                                            .sorts"
                                        :key="sort.value"
                                        :value="sort.value"
                                    >
                                        {{ sort.label }}
                                    </option>
                                </select>
                            </label>

                            <label
                                v-for="group in timeline.filter_options
                                    .category_groups"
                                :key="group.slug"
                                class="block"
                            >
                                <span
                                    class="mb-1 block text-sm font-semibold text-[#094067]"
                                    >{{ group.name }}</span
                                >
                                <select
                                    v-model="form.categories[group.slug]"
                                    class="h-11 w-full rounded-sm border border-[#90b4ce]/60 bg-[#fffffe] px-3 text-sm outline-none focus:border-[#3da9fc]"
                                >
                                    <option value="">全部</option>
                                    <option
                                        v-for="child in group.children"
                                        :key="child.id"
                                        :value="child.id.toString()"
                                    >
                                        {{ child.name }}
                                    </option>
                                </select>
                            </label>

                            <fieldset
                                v-if="timeline.filter_options.tags.length"
                                class="space-y-2"
                            >
                                <legend
                                    class="text-sm font-semibold text-[#094067]"
                                >
                                    标签
                                </legend>
                                <label
                                    v-for="tag in timeline.filter_options.tags.slice(
                                        0,
                                        16,
                                    )"
                                    :key="tag.id"
                                    class="flex items-center gap-2 text-sm text-[#5f6c7b]"
                                >
                                    <input
                                        v-model="form.tags"
                                        type="checkbox"
                                        :value="tag.id.toString()"
                                        class="h-4 w-4 rounded border-[#90b4ce] text-[#3da9fc]"
                                    />
                                    <span>{{ tag.name }}</span>
                                </label>
                            </fieldset>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-sm bg-[#3da9fc] px-4 text-sm font-semibold text-[#fffffe] hover:bg-[#094067]"
                            >
                                <Search class="h-4 w-4" aria-hidden="true" />
                                筛选
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                                @click="resetFilters"
                            >
                                <RotateCcw class="h-4 w-4" aria-hidden="true" />
                                重置
                            </button>
                        </div>
                    </form>

                    <section
                        v-if="visibleMonths.length"
                        class="rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] p-4 shadow-sm"
                    >
                        <h2 class="text-base font-semibold text-[#094067]">
                            月份入口
                        </h2>
                        <div class="mt-3 grid gap-2">
                            <Link
                                v-for="month in visibleMonths"
                                :key="month.month"
                                :href="month.url"
                                class="flex h-10 items-center justify-between rounded-sm bg-[#d8eefe] px-3 text-sm font-semibold text-[#094067] hover:bg-[#3da9fc] hover:text-[#fffffe]"
                            >
                                <span>{{ month.label }}</span>
                                <span>{{ month.photos_count }}</span>
                            </Link>
                        </div>
                    </section>
                </aside>

                <div>
                    <div
                        v-if="hasPhotos"
                        class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <Link
                            v-for="photo in timeline.photos.data"
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
                            <div class="grid min-h-40 gap-3 p-4">
                                <div>
                                    <h2
                                        class="line-clamp-2 text-base leading-6 font-semibold text-[#094067]"
                                    >
                                        {{ photo.title }}
                                    </h2>
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
                                <div
                                    v-if="photo.tags.length"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span
                                        v-for="tag in photo.tags.slice(0, 3)"
                                        :key="tag.id"
                                        class="rounded-full bg-[#d8eefe] px-2.5 py-1 text-xs font-medium text-[#094067]"
                                    >
                                        {{ tag.name }}
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div
                        v-else
                        class="rounded-sm bg-[#d8eefe] px-6 py-14 text-center"
                    >
                        <h2 class="text-2xl font-semibold text-[#094067]">
                            当前时间范围暂无公开图片
                        </h2>
                        <p
                            class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]"
                        >
                            时间轴只展示已发布、可公开展示并且填写了事件日期的图片。可以调整筛选条件或返回总时间线。
                        </p>
                    </div>

                    <nav
                        v-if="timeline.photos.meta.last_page > 1"
                        class="mt-10 flex items-center justify-between gap-4 border-t border-[#90b4ce]/30 pt-6"
                        aria-label="时间轴分页"
                    >
                        <Link
                            v-if="timeline.photos.links.prev"
                            :href="timeline.photos.links.prev"
                            class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                        >
                            <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                            上一页
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/30 px-4 text-sm font-semibold text-[#90b4ce]"
                        >
                            <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                            上一页
                        </span>

                        <p class="text-sm text-[#5f6c7b]">
                            第 {{ timeline.photos.meta.current_page }} /
                            {{ timeline.photos.meta.last_page }} 页
                        </p>

                        <Link
                            v-if="timeline.photos.links.next"
                            :href="timeline.photos.links.next"
                            class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                        >
                            下一页
                            <ChevronRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/30 px-4 text-sm font-semibold text-[#90b4ce]"
                        >
                            下一页
                            <ChevronRight class="h-4 w-4" aria-hidden="true" />
                        </span>
                    </nav>

                    <section v-if="timeline.albums.length" class="mt-12">
                        <h2 class="mb-5 text-2xl font-semibold text-[#094067]">
                            相关相册
                        </h2>
                        <div class="grid gap-5 md:grid-cols-2">
                            <Link
                                v-for="album in timeline.albums"
                                :key="album.id"
                                :href="album.url"
                                class="group grid overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm sm:grid-cols-[160px_minmax(0,1fr)]"
                            >
                                <div
                                    class="aspect-[4/3] overflow-hidden bg-[#d8eefe] sm:aspect-auto sm:h-full"
                                >
                                    <img
                                        v-if="
                                            album.cover_image_url &&
                                            !imageFailures[`album-${album.id}`]
                                        "
                                        :src="album.cover_image_url"
                                        :alt="album.cover_alt"
                                        loading="lazy"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                        @error="
                                            imageFailures[`album-${album.id}`] =
                                                true
                                        "
                                    />
                                    <div
                                        v-else
                                        class="flex h-full min-h-32 w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                                    >
                                        <CalendarDays
                                            class="h-7 w-7"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h3
                                        class="line-clamp-2 font-semibold text-[#094067]"
                                    >
                                        {{ album.title }}
                                    </h3>
                                    <p class="mt-2 text-sm text-[#5f6c7b]">
                                        {{
                                            album.public_photos_count
                                        }}
                                        张公开图片
                                    </p>
                                    <p
                                        v-if="album.category_summary"
                                        class="mt-3 truncate text-sm text-[#5f6c7b]"
                                    >
                                        {{ album.category_summary }}
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section
                        v-if="timeline.related_topics.length"
                        class="mt-12"
                    >
                        <h2 class="mb-5 text-2xl font-semibold text-[#094067]">
                            相关专题
                        </h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <Link
                                v-for="topic in timeline.related_topics"
                                :key="topic.url"
                                :href="topic.url"
                                class="rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] p-4 shadow-sm hover:border-[#3da9fc]"
                            >
                                <h3 class="font-semibold text-[#094067]">
                                    {{ topic.title }}
                                </h3>
                                <p
                                    v-if="topic.description"
                                    class="mt-2 line-clamp-2 text-sm leading-6 text-[#5f6c7b]"
                                >
                                    {{ topic.description }}
                                </p>
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </main>
</template>
