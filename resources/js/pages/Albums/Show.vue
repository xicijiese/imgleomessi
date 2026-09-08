<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ImageOff,
    Images,
} from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface SortOption {
    value: string;
    label: string;
}

interface CategorySummary {
    id: number;
    name: string;
    root_name: string | null;
    root_slug: string | null;
}

interface TagSummary {
    id: number;
    name: string;
    type: string;
}

interface AlbumHeader {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    url: string;
    cover_image_url: string | null;
    cover_alt: string;
    published_at: string | null;
    public_photos_count: number;
    category_summary: string;
    categories: CategorySummary[];
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
    categories: CategorySummary[];
    tags: TagSummary[];
}

interface AlbumDetailPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    filters: {
        sort: string;
    };
    filter_options: {
        sorts: SortOption[];
    };
    album: AlbumHeader;
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
    albumDetail: AlbumDetailPayload;
}>();

const imageFailures = ref<Record<number, boolean>>({});
const coverFailed = ref(false);

const form = reactive({
    sort: props.albumDetail.filters.sort,
});

const applySort = () => {
    router.get(
        `/albums/${props.albumDetail.album.slug}`,
        {
            sort: form.sort !== 'event_desc' ? form.sort : undefined,
        },
        {
            preserveScroll: false,
            preserveState: false,
            replace: true,
        },
    );
};

const formatDate = (date: string | null) => date ?? '日期待补充';
</script>

<template>
    <SeoHead :seo="albumDetail.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="albumDetail.navigation"
            :site="albumDetail.site"
        />

        <section class="bg-[#d8eefe] px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)] lg:items-center"
            >
                <div class="min-w-0">
                    <Link
                        href="/albums"
                        class="mb-6 inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                        返回相册
                    </Link>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Album
                    </p>
                    <h1
                        class="max-w-4xl text-4xl leading-tight font-semibold sm:text-5xl"
                    >
                        {{ albumDetail.album.title }}
                    </h1>
                    <p
                        v-if="albumDetail.album.description"
                        class="mt-5 max-w-3xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        {{ albumDetail.album.description }}
                    </p>
                    <div
                        class="mt-5 flex flex-wrap gap-3 text-sm text-[#5f6c7b]"
                    >
                        <span
                            class="inline-flex min-h-11 items-center gap-2 rounded-sm bg-[#fffffe] px-3 shadow-sm"
                        >
                            <CalendarDays
                                class="h-4 w-4 text-[#3da9fc]"
                                aria-hidden="true"
                            />
                            {{ formatDate(albumDetail.album.published_at) }}
                        </span>
                        <span
                            class="inline-flex min-h-11 items-center gap-2 rounded-sm bg-[#fffffe] px-3 shadow-sm"
                        >
                            <Images
                                class="h-4 w-4 text-[#3da9fc]"
                                aria-hidden="true"
                            />
                            {{ albumDetail.album.public_photos_count }}
                            张公开图片
                        </span>
                    </div>
                    <div
                        v-if="albumDetail.album.categories.length"
                        class="mt-5 flex flex-wrap gap-2"
                    >
                        <span
                            v-for="category in albumDetail.album.categories"
                            :key="category.id"
                            class="rounded-full bg-[#fffffe] px-3 py-1.5 text-xs font-medium text-[#094067] shadow-sm"
                        >
                            {{ category.root_name }} / {{ category.name }}
                        </span>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] shadow-sm"
                >
                    <div class="aspect-[16/10] bg-[#d8eefe]">
                        <img
                            v-if="
                                albumDetail.album.cover_image_url &&
                                !coverFailed
                            "
                            :src="albumDetail.album.cover_image_url"
                            :alt="albumDetail.album.cover_alt"
                            class="h-full w-full object-cover"
                            @error="coverFailed = true"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                        >
                            <ImageOff class="h-10 w-10" aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div
                    class="mb-6 flex flex-wrap items-center justify-between gap-4"
                >
                    <div>
                        <h2 class="text-2xl font-semibold text-[#094067]">
                            相册图片
                        </h2>
                        <p class="mt-2 text-sm text-[#5f6c7b]">
                            共 {{ albumDetail.photos.meta.total }} 张公开图片
                            <span v-if="albumDetail.photos.meta.from !== null"
                                >，当前 {{ albumDetail.photos.meta.from }}-{{
                                    albumDetail.photos.meta.to
                                }}</span
                            >
                        </p>
                    </div>
                    <form
                        class="flex items-center gap-2"
                        @submit.prevent="applySort"
                    >
                        <label class="sr-only" for="album-photo-sort"
                            >排序</label
                        >
                        <select
                            id="album-photo-sort"
                            v-model="form.sort"
                            class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                            @change="applySort"
                        >
                            <option
                                v-for="sort in albumDetail.filter_options.sorts"
                                :key="sort.value"
                                :value="sort.value"
                            >
                                {{ sort.label }}
                            </option>
                        </select>
                    </form>
                </div>

                <div
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Link
                        v-for="photo in albumDetail.photos.data"
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
                        <div class="grid min-h-36 gap-3 p-4">
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

                <nav
                    v-if="albumDetail.photos.meta.last_page > 1"
                    class="mt-10 flex items-center justify-between gap-4 border-t border-[#90b4ce]/30 pt-6"
                    aria-label="相册图片分页"
                >
                    <Link
                        v-if="albumDetail.photos.links.prev"
                        :href="albumDetail.photos.links.prev"
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
                        第 {{ albumDetail.photos.meta.current_page }} /
                        {{ albumDetail.photos.meta.last_page }} 页
                    </p>

                    <Link
                        v-if="albumDetail.photos.links.next"
                        :href="albumDetail.photos.links.next"
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
