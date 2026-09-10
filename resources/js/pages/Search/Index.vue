<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import PublicPhotoFilters from '@/components/PublicPhotoFilters.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ImageOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
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

interface TagOption {
    id: number;
    name: string;
}

interface AlbumOption {
    id: number;
    title: string;
}

interface SelectOption {
    value: string;
    label: string;
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
    published_at: string | null;
    category_summary: string;
    resolution_label: string;
    orientation_label: string;
    watermark_status_label: string;
    tags: TagOption[];
}

interface SearchOperationItem {
    term: string;
    label: string;
    description: string | null;
    url: string;
    source: 'recommendation' | 'hot';
}

interface SearchOperationsPayload {
    recommendations: SearchOperationItem[];
    hot_terms: SearchOperationItem[];
}

interface SearchPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    filters: {
        q: string;
        categories: Record<string, number>;
        tags: number[];
        people_tags: number[];
        album_id: number | null;
        source_mode: string;
        copyright_status: string | null;
        orientation: string;
        resolution: string;
        watermark_status: string | null;
        date_from: string | null;
        date_to: string | null;
        sort: string;
    };
    filter_options: {
        category_groups: CategoryGroup[];
        tags: TagOption[];
        people_tags: TagOption[];
        albums: AlbumOption[];
        source_modes: SelectOption[];
        copyright_statuses: SelectOption[];
        orientations: SelectOption[];
        resolutions: SelectOption[];
        watermark_statuses: SelectOption[];
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
}

const props = defineProps<{
    search: SearchPayload;
    search_operations: SearchOperationsPayload;
}>();

const imageFailures = ref<Record<number, boolean>>({});
const hasPhotos = computed(() => props.search.photos.data.length > 0);
const hasSearchIdeas = computed(
    () =>
        props.search_operations.recommendations.length > 0 ||
        props.search_operations.hot_terms.length > 0,
);
const showSearchIdeas = computed(
    () =>
        hasSearchIdeas.value &&
        (!props.search.filters.q.trim() || props.search.photos.meta.total === 0),
);
const resultTitle = computed(() =>
    props.search.filters.q.trim()
        ? `“${props.search.filters.q}” 的搜索结果`
        : '搜索梅西图片档案',
);
const emptyTitle = computed(() =>
    props.search.photos.meta.total === 0
        ? '没有找到匹配结果'
        : '还没有可展示的公开图片',
);
const emptyDescription = computed(() =>
    props.search.photos.meta.total === 0
        ? '当前搜索条件下没有结果，可以清空关键词或筛选条件后重新搜索。'
        : '后台发布图片后，这里会展示最新公开图片。',
);
const formatDate = (date: string | null) => date ?? '日期待补充';
</script>

<template>
    <Head title="搜索" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="search.navigation" :site="search.site" />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Search
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">
                        {{ resultTitle }}
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        通过标题、说明、分类、标签、相册、来源和图片资料查找已发布的公开图片。
                    </p>
                </div>

                <div
                    class="rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm"
                >
                    <span class="font-semibold text-[#094067]">{{
                        search.photos.meta.total
                    }}</span>
                    张搜索结果
                    <span v-if="search.photos.meta.from !== null" class="ml-2">
                        当前 {{ search.photos.meta.from }}-{{
                            search.photos.meta.to
                        }}
                    </span>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <PublicPhotoFilters
                    :filters="search.filters"
                    :filter-options="search.filter_options"
                    submit-path="/search"
                    submit-label="搜索"
                />

                <section
                    v-if="showSearchIdeas"
                    class="mb-8 border-y border-[#90b4ce]/30 py-5"
                    aria-label="搜索建议"
                >
                    <div
                        v-if="search_operations.recommendations.length"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <h2
                            class="w-24 shrink-0 text-sm font-semibold text-[#094067]"
                        >
                            推荐搜索
                        </h2>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="item in search_operations.recommendations"
                                :key="`recommendation-${item.term}`"
                                :href="item.url"
                                class="rounded-full border border-[#90b4ce]/60 px-3 py-1.5 text-sm font-medium text-[#094067] transition hover:border-[#3da9fc] hover:text-[#3da9fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="search_operations.hot_terms.length"
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <h2
                            class="w-24 shrink-0 text-sm font-semibold text-[#094067]"
                        >
                            热门搜索
                        </h2>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="item in search_operations.hot_terms"
                                :key="`hot-${item.term}`"
                                :href="item.url"
                                class="rounded-full bg-[#d8eefe] px-3 py-1.5 text-sm font-medium text-[#094067] transition hover:bg-[#90b4ce]/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>
                </section>

                <div
                    v-if="hasPhotos"
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Link
                        v-for="photo in search.photos.data"
                        :key="photo.uuid"
                        :href="photo.url"
                        class="group overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                        :aria-label="`查看图片：${photo.title}`"
                    >
                        <div class="aspect-[4/3] overflow-hidden bg-[#d8eefe]">
                            <img
                                v-if="
                                    photo.image_url && !imageFailures[photo.id]
                                "
                                :src="photo.image_url"
                                :alt="photo.alt"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                @error="imageFailures[photo.id] = true"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                            >
                                <ImageOff class="h-8 w-8" aria-hidden="true" />
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
                                class="flex flex-wrap gap-2 text-xs font-medium"
                            >
                                <span
                                    class="rounded-full bg-[#d8eefe] px-2.5 py-1 text-[#094067]"
                                >
                                    {{ photo.resolution_label }}
                                </span>
                                <span
                                    class="rounded-full bg-[#d8eefe] px-2.5 py-1 text-[#094067]"
                                >
                                    {{ photo.orientation_label }}
                                </span>
                                <span
                                    class="rounded-full bg-[#d8eefe] px-2.5 py-1 text-[#094067]"
                                >
                                    {{ photo.watermark_status_label }}
                                </span>
                            </div>
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
                        {{ emptyTitle }}
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]"
                    >
                        {{ emptyDescription }}
                    </p>
                </div>

                <nav
                    v-if="search.photos.meta.last_page > 1"
                    class="mt-10 flex items-center justify-between gap-4 border-t border-[#90b4ce]/30 pt-6"
                    aria-label="搜索结果分页"
                >
                    <Link
                        v-if="search.photos.links.prev"
                        :href="search.photos.links.prev"
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
                        第 {{ search.photos.meta.current_page }} /
                        {{ search.photos.meta.last_page }} 页
                    </p>

                    <Link
                        v-if="search.photos.links.next"
                        :href="search.photos.links.next"
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
            </div>
        </section>
    </main>
</template>
