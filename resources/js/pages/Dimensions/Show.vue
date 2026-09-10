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

interface SelectOption {
    value: string;
    label: string;
}

interface YearOption {
    value: number;
    label: string;
}

interface TagPayload {
    id: number;
    name: string;
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

interface ArchiveShowPayload {
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
        index_url: string;
        gallery_url: string;
        timeline_url: string;
        breadcrumbs: BreadcrumbItem[];
    };
    category: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
    };
    summary: {
        photos_count: number;
        albums_count: number;
        years_count: number;
    };
    filters: {
        q: string;
        years: number[];
        tags: number[];
        people_tags: number[];
        sort: string;
    };
    filter_options: {
        years: YearOption[];
        tags: TagPayload[];
        people_tags: TagPayload[];
        sorts: SelectOption[];
    };
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
    archive: ArchiveShowPayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});
const form = reactive({
    q: props.archive.filters.q,
    sort: props.archive.filters.sort,
    years: props.archive.filters.years.map((year) => year.toString()),
    tags: props.archive.filters.tags.map((id) => id.toString()),
    people_tags: props.archive.filters.people_tags.map((id) => id.toString()),
});
const hasPhotos = computed(() => props.archive.photos.data.length > 0);
const formatDate = (date: string | null) => date ?? '日期待补充';

const selectedQuery = () => ({
    q: form.q.trim() || undefined,
    sort: form.sort === 'event_desc' ? undefined : form.sort,
    years: form.years.length ? form.years : undefined,
    tags: form.tags.length ? form.tags : undefined,
    people_tags: form.people_tags.length ? form.people_tags : undefined,
});

const applyFilters = () => {
    router.get(
        props.archive.dimension.breadcrumbs[1].url,
        selectedQuery(),
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    form.q = '';
    form.sort = 'event_desc';
    form.years = [];
    form.tags = [];
    form.people_tags = [];
    router.get(props.archive.dimension.breadcrumbs[1].url, {}, { preserveScroll: true, replace: true });
};
</script>

<template>
    <SeoHead :seo="archive.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="archive.navigation" :site="archive.site" />

        <section class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <nav class="mb-5 flex flex-wrap gap-2 text-sm text-[#5f6c7b]" aria-label="面包屑">
                    <template v-for="(item, index) in archive.dimension.breadcrumbs" :key="item.url">
                        <Link :href="item.url" class="font-semibold text-[#094067] hover:text-[#3da9fc]">
                            {{ item.label }}
                        </Link>
                        <span v-if="index < archive.dimension.breadcrumbs.length - 1">/</span>
                    </template>
                </nav>

                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                            {{ archive.dimension.eyebrow }}
                        </p>
                        <h1 class="text-4xl font-semibold sm:text-5xl">
                            {{ archive.category.name }}
                        </h1>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base">
                            {{ archive.category.description || `浏览${archive.category.name}相关的公开图片、相册和时间线入口。` }}
                        </p>
                    </div>

                    <div class="grid gap-2 rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm sm:grid-cols-3 sm:gap-5">
                        <span><strong class="text-[#094067]">{{ archive.summary.photos_count }}</strong> 张图片</span>
                        <span><strong class="text-[#094067]">{{ archive.summary.albums_count }}</strong> 个相册</span>
                        <span><strong class="text-[#094067]">{{ archive.summary.years_count }}</strong> 个年份</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                    <section class="rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] p-4 shadow-sm">
                        <h2 class="text-base font-semibold text-[#094067]">
                            {{ archive.dimension.label }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">
                            {{ archive.category.name }} 当前聚合 {{ archive.summary.photos_count }} 张公开图片。
                        </p>
                        <div class="mt-4 grid gap-3">
                            <Link :href="archive.dimension.timeline_url" class="inline-flex h-10 items-center justify-center gap-2 rounded-sm bg-[#3da9fc] px-3 text-sm font-semibold text-[#fffffe] hover:bg-[#094067]">
                                <CalendarDays class="h-4 w-4" aria-hidden="true" />
                                查看时间线
                            </Link>
                            <Link :href="archive.dimension.gallery_url" class="inline-flex h-10 items-center justify-center rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]">
                                返回图库
                            </Link>
                        </div>
                    </section>

                    <form class="rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] p-4 shadow-sm" @submit.prevent="applyFilters">
                        <div class="space-y-4">
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-[#094067]">关键词</span>
                                <span class="flex h-11 items-center rounded-sm border border-[#90b4ce]/60 px-3">
                                    <Search class="mr-2 h-4 w-4 shrink-0 text-[#5f6c7b]" aria-hidden="true" />
                                    <input v-model="form.q" type="search" class="w-full bg-transparent text-sm outline-none placeholder:text-[#5f6c7b]" placeholder="标题或描述" />
                                </span>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-[#094067]">排序</span>
                                <select v-model="form.sort" class="h-11 w-full rounded-sm border border-[#90b4ce]/60 bg-[#fffffe] px-3 text-sm outline-none focus:border-[#3da9fc]">
                                    <option v-for="sort in archive.filter_options.sorts" :key="sort.value" :value="sort.value">
                                        {{ sort.label }}
                                    </option>
                                </select>
                            </label>

                            <fieldset v-if="archive.filter_options.years.length" class="space-y-2">
                                <legend class="text-sm font-semibold text-[#094067]">年份</legend>
                                <label v-for="year in archive.filter_options.years" :key="year.value" class="flex items-center gap-2 text-sm text-[#5f6c7b]">
                                    <input v-model="form.years" type="checkbox" :value="year.value.toString()" class="h-4 w-4 rounded border-[#90b4ce] text-[#3da9fc]" />
                                    <span>{{ year.label }}</span>
                                </label>
                            </fieldset>

                            <fieldset v-if="archive.filter_options.tags.length" class="space-y-2">
                                <legend class="text-sm font-semibold text-[#094067]">标签</legend>
                                <label v-for="tag in archive.filter_options.tags.slice(0, 14)" :key="tag.id" class="flex items-center gap-2 text-sm text-[#5f6c7b]">
                                    <input v-model="form.tags" type="checkbox" :value="tag.id.toString()" class="h-4 w-4 rounded border-[#90b4ce] text-[#3da9fc]" />
                                    <span>{{ tag.name }}</span>
                                </label>
                            </fieldset>

                            <fieldset v-if="archive.filter_options.people_tags.length" class="space-y-2">
                                <legend class="text-sm font-semibold text-[#094067]">人物同框</legend>
                                <label v-for="tag in archive.filter_options.people_tags.slice(0, 10)" :key="tag.id" class="flex items-center gap-2 text-sm text-[#5f6c7b]">
                                    <input v-model="form.people_tags" type="checkbox" :value="tag.id.toString()" class="h-4 w-4 rounded border-[#90b4ce] text-[#3da9fc]" />
                                    <span>{{ tag.name }}</span>
                                </label>
                            </fieldset>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-sm bg-[#3da9fc] px-4 text-sm font-semibold text-[#fffffe] hover:bg-[#094067]">
                                <Search class="h-4 w-4" aria-hidden="true" />
                                筛选
                            </button>
                            <button type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]" @click="resetFilters">
                                <RotateCcw class="h-4 w-4" aria-hidden="true" />
                                重置
                            </button>
                        </div>
                    </form>
                </aside>

                <div>
                    <div v-if="hasPhotos" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <Link v-for="photo in archive.photos.data" :key="photo.uuid" :href="photo.url" class="group overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]" :aria-label="`查看图片：${photo.title}`">
                            <div class="aspect-[4/3] overflow-hidden bg-[#d8eefe]">
                                <img
                                    v-if="photo.image_url && !imageFailures[`photo-${photo.id}`]"
                                    :src="photo.image_url"
                                    :alt="photo.alt"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                    @error="imageFailures[`photo-${photo.id}`] = true"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]">
                                    <ImageOff class="h-8 w-8" aria-hidden="true" />
                                </div>
                            </div>
                            <div class="grid min-h-40 gap-3 p-4">
                                <div>
                                    <h2 class="line-clamp-2 text-base leading-6 font-semibold text-[#094067]">
                                        {{ photo.title }}
                                    </h2>
                                    <p class="mt-2 text-sm text-[#5f6c7b]">
                                        {{ formatDate(photo.event_date) }}
                                    </p>
                                </div>
                                <p v-if="photo.category_summary" class="truncate text-sm text-[#5f6c7b]">
                                    {{ photo.category_summary }}
                                </p>
                                <div v-if="photo.tags.length" class="flex flex-wrap gap-2">
                                    <span v-for="tag in photo.tags.slice(0, 3)" :key="tag.id" class="rounded-full bg-[#d8eefe] px-2.5 py-1 text-xs font-medium text-[#094067]">
                                        {{ tag.name }}
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="rounded-sm bg-[#d8eefe] px-6 py-14 text-center">
                        <h2 class="text-2xl font-semibold text-[#094067]">
                            当前维度暂无公开图片
                        </h2>
                        <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]">
                            聚合页只展示已发布、可公开展示的图片。可以调整筛选条件，或返回图库继续查找。
                        </p>
                    </div>

                    <nav v-if="archive.photos.meta.last_page > 1" class="mt-10 flex items-center justify-between gap-4 border-t border-[#90b4ce]/30 pt-6" aria-label="聚合分页">
                        <Link v-if="archive.photos.links.prev" :href="archive.photos.links.prev" class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]">
                            <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                            上一页
                        </Link>
                        <span v-else class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/30 px-4 text-sm font-semibold text-[#90b4ce]">
                            <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                            上一页
                        </span>

                        <p class="text-sm text-[#5f6c7b]">
                            第 {{ archive.photos.meta.current_page }} / {{ archive.photos.meta.last_page }} 页
                        </p>

                        <Link v-if="archive.photos.links.next" :href="archive.photos.links.next" class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]">
                            下一页
                            <ChevronRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <span v-else class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/30 px-4 text-sm font-semibold text-[#90b4ce]">
                            下一页
                            <ChevronRight class="h-4 w-4" aria-hidden="true" />
                        </span>
                    </nav>

                    <section v-if="archive.albums.length" class="mt-12">
                        <h2 class="mb-5 text-2xl font-semibold text-[#094067]">
                            相关相册
                        </h2>
                        <div class="grid gap-5 md:grid-cols-2">
                            <Link v-for="album in archive.albums" :key="album.id" :href="album.url" class="group grid overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm sm:grid-cols-[160px_minmax(0,1fr)]">
                                <div class="aspect-[4/3] overflow-hidden bg-[#d8eefe] sm:aspect-auto sm:h-full">
                                    <img
                                        v-if="album.cover_image_url && !imageFailures[`album-${album.id}`]"
                                        :src="album.cover_image_url"
                                        :alt="album.cover_alt"
                                        loading="lazy"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                        @error="imageFailures[`album-${album.id}`] = true"
                                    />
                                    <div v-else class="flex h-full min-h-32 w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]">
                                        <CalendarDays class="h-7 w-7" aria-hidden="true" />
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h3 class="line-clamp-2 font-semibold text-[#094067]">
                                        {{ album.title }}
                                    </h3>
                                    <p class="mt-2 text-sm text-[#5f6c7b]">
                                        {{ album.public_photos_count }} 张公开图片
                                    </p>
                                    <p v-if="album.category_summary" class="mt-3 truncate text-sm text-[#5f6c7b]">
                                        {{ album.category_summary }}
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section v-if="archive.related_topics.length" class="mt-12">
                        <h2 class="mb-5 text-2xl font-semibold text-[#094067]">
                            相关专题
                        </h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <Link v-for="topic in archive.related_topics" :key="topic.url" :href="topic.url" class="rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] p-4 shadow-sm hover:border-[#3da9fc]">
                                <h3 class="font-semibold text-[#094067]">
                                    {{ topic.title }}
                                </h3>
                                <p v-if="topic.description" class="mt-2 line-clamp-2 text-sm leading-6 text-[#5f6c7b]">
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
