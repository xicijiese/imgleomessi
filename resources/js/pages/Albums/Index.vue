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
    Images,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

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

interface SortOption {
    value: string;
    label: string;
}

interface AlbumCard {
    id: number;
    title: string;
    slug: string;
    description: string;
    url: string;
    cover_image_url: string | null;
    cover_alt: string;
    published_at: string | null;
    public_photos_count: number;
    category_summary: string;
    categories: Array<{
        id: number;
        name: string;
        root_name: string | null;
        root_slug: string | null;
    }>;
}

interface AlbumIndexPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    filters: {
        q: string;
        categories: Record<string, number>;
        sort: string;
    };
    filter_options: {
        category_groups: CategoryGroup[];
        sorts: SortOption[];
    };
    albums: {
        data: AlbumCard[];
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
    albumIndex: AlbumIndexPayload;
}>();

const filtersOpen = ref(false);
const imageFailures = ref<Record<number, boolean>>({});

const form = reactive({
    q: props.albumIndex.filters.q,
    categories: Object.fromEntries(
        Object.entries(props.albumIndex.filters.categories).map(
            ([slug, id]) => [slug, String(id)],
        ),
    ) as Record<string, string>,
    sort: props.albumIndex.filters.sort,
});

const hasAlbums = computed(() => props.albumIndex.albums.data.length > 0);
const hasActiveFilters = computed(
    () =>
        Boolean(form.q.trim()) ||
        Object.values(form.categories).some(Boolean) ||
        form.sort !== 'default',
);
const emptyTitle = computed(() =>
    hasActiveFilters.value ? '没有找到匹配相册' : '还没有可展示的公开相册',
);
const emptyDescription = computed(() =>
    hasActiveFilters.value
        ? '当前条件下没有公开相册，可以清空筛选后重新浏览。'
        : '后台发布相册并加入公开图片后，这里会展示相册集合。',
);

const queryParams = () => {
    const categories = Object.fromEntries(
        Object.entries(form.categories).filter(([, value]) => value !== ''),
    );

    return {
        q: form.q.trim() || undefined,
        categories: Object.keys(categories).length > 0 ? categories : undefined,
        sort: form.sort !== 'default' ? form.sort : undefined,
    };
};

const applyFilters = () => {
    router.get('/albums', queryParams(), {
        preserveScroll: false,
        preserveState: false,
        replace: true,
    });
    filtersOpen.value = false;
};

const resetFilters = () => {
    router.get(
        '/albums',
        {},
        {
            preserveScroll: false,
            preserveState: false,
            replace: true,
        },
    );
    filtersOpen.value = false;
};

const formatDate = (date: string | null) => date ?? '发布时间待补充';
</script>

<template>
    <SeoHead :seo="albumIndex.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="albumIndex.navigation"
            :site="albumIndex.site"
        />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Albums
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">相册</h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        按赛事、年份、球队和主题浏览已发布的梅西影像集合。
                    </p>
                </div>

                <div
                    class="rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm"
                >
                    <span class="font-semibold text-[#094067]">{{
                        albumIndex.albums.meta.total
                    }}</span>
                    个公开相册
                    <span
                        v-if="albumIndex.albums.meta.from !== null"
                        class="ml-2"
                    >
                        当前 {{ albumIndex.albums.meta.from }}-{{
                            albumIndex.albums.meta.to
                        }}
                    </span>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div
                    class="mb-5 flex flex-wrap items-center justify-between gap-3"
                >
                    <button
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067]"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <SlidersHorizontal class="h-4 w-4" aria-hidden="true" />
                        筛选
                    </button>
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="h-11 text-sm font-semibold text-[#3da9fc]"
                        @click="resetFilters"
                    >
                        清空全部
                    </button>
                </div>

                <form
                    class="mb-8 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm lg:p-5"
                    :class="filtersOpen ? 'block' : 'hidden lg:block'"
                    @submit.prevent="applyFilters"
                >
                    <div class="grid gap-4 lg:grid-cols-12">
                        <label class="grid gap-2 lg:col-span-5">
                            <span class="text-sm font-semibold">关键词</span>
                            <input
                                v-model="form.q"
                                type="search"
                                class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                                placeholder="标题、说明或 URL 标识"
                            />
                        </label>

                        <label class="grid gap-2 lg:col-span-3">
                            <span class="text-sm font-semibold">排序</span>
                            <select
                                v-model="form.sort"
                                class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                            >
                                <option
                                    v-for="sort in albumIndex.filter_options
                                        .sorts"
                                    :key="sort.value"
                                    :value="sort.value"
                                >
                                    {{ sort.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div
                        v-if="albumIndex.filter_options.category_groups.length"
                        class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <label
                            v-for="group in albumIndex.filter_options
                                .category_groups"
                            :key="group.slug"
                            class="grid gap-2"
                        >
                            <span class="text-sm font-semibold">{{
                                group.name
                            }}</span>
                            <select
                                v-model="form.categories[group.slug]"
                                class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                            >
                                <option value="">全部</option>
                                <option
                                    v-for="child in group.children"
                                    :key="child.id"
                                    :value="String(child.id)"
                                >
                                    {{ child.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            class="h-11 rounded-sm bg-[#3da9fc] px-5 text-sm font-semibold text-[#fffffe] transition hover:bg-[#1e94e6]"
                        >
                            应用筛选
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="h-11 rounded-sm border border-[#90b4ce]/60 px-5 text-sm font-semibold"
                            @click="resetFilters"
                        >
                            清空全部
                        </button>
                    </div>
                </form>

                <div
                    v-if="hasAlbums"
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Link
                        v-for="album in albumIndex.albums.data"
                        :key="album.slug"
                        :href="album.url"
                        class="group overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                        :aria-label="`查看相册：${album.title}`"
                    >
                        <div
                            class="aspect-[16/10] overflow-hidden bg-[#d8eefe]"
                        >
                            <img
                                v-if="
                                    album.cover_image_url &&
                                    !imageFailures[album.id]
                                "
                                :src="album.cover_image_url"
                                :alt="album.cover_alt"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                @error="imageFailures[album.id] = true"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                            >
                                <ImageOff class="h-8 w-8" aria-hidden="true" />
                            </div>
                        </div>
                        <div class="grid min-h-44 gap-3 p-4">
                            <div>
                                <h2
                                    class="line-clamp-2 text-base leading-6 font-semibold text-[#094067]"
                                >
                                    {{ album.title }}
                                </h2>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-3 text-sm text-[#5f6c7b]"
                                >
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <CalendarDays
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        {{ formatDate(album.published_at) }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <Images
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        {{ album.public_photos_count }} 张
                                    </span>
                                </div>
                            </div>
                            <p
                                v-if="album.description"
                                class="line-clamp-3 text-sm leading-6 text-[#5f6c7b]"
                            >
                                {{ album.description }}
                            </p>
                            <p
                                v-if="album.category_summary"
                                class="truncate text-sm text-[#5f6c7b]"
                            >
                                {{ album.category_summary }}
                            </p>
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
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="mt-6 h-11 rounded-sm bg-[#3da9fc] px-5 text-sm font-semibold text-[#fffffe]"
                        @click="resetFilters"
                    >
                        清空全部
                    </button>
                </div>

                <nav
                    v-if="albumIndex.albums.meta.last_page > 1"
                    class="mt-10 flex items-center justify-between gap-4 border-t border-[#90b4ce]/30 pt-6"
                    aria-label="相册分页"
                >
                    <Link
                        v-if="albumIndex.albums.links.prev"
                        :href="albumIndex.albums.links.prev"
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
                        第 {{ albumIndex.albums.meta.current_page }} /
                        {{ albumIndex.albums.meta.last_page }} 页
                    </p>

                    <Link
                        v-if="albumIndex.albums.links.next"
                        :href="albumIndex.albums.links.next"
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
