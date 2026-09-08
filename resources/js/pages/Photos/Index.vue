<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import PublicPhotoFilters from '@/components/PublicPhotoFilters.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
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
    type: string;
}

interface AlbumOption {
    id: number;
    title: string;
}

interface SourceOption {
    id: number;
    label: string;
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

interface GalleryPayload {
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
        tags: number[];
        people_tags: number[];
        album_id: number | null;
        source_mode: string;
        source_id: number | null;
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
        sources: SourceOption[];
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
    gallery: GalleryPayload;
}>();

const imageFailures = ref<Record<number, boolean>>({});
const hasPhotos = computed(() => props.gallery.photos.data.length > 0);
const formatDate = (date: string | null) => date ?? '日期待补充';
</script>

<template>
    <SeoHead :seo="gallery.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="gallery.navigation" :site="gallery.site" />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Photos
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">图库</h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        浏览已发布的梅西图片档案，通过分类、标签、相册、来源和图片资料快速缩小范围。
                    </p>
                </div>

                <div
                    class="rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm"
                >
                    <span class="font-semibold text-[#094067]">{{
                        gallery.photos.meta.total
                    }}</span>
                    张公开图片
                    <span v-if="gallery.photos.meta.from !== null" class="ml-2">
                        当前 {{ gallery.photos.meta.from }}-{{
                            gallery.photos.meta.to
                        }}
                    </span>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <PublicPhotoFilters
                    :filters="gallery.filters"
                    :filter-options="gallery.filter_options"
                    submit-path="/photos"
                    submit-label="应用筛选"
                />

                <div
                    v-if="hasPhotos"
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Link
                        v-for="photo in gallery.photos.data"
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
                        没有找到公开图片
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]"
                    >
                        当前筛选条件下没有结果，可以清空筛选后重新浏览图库。
                    </p>
                </div>

                <nav
                    v-if="gallery.photos.meta.last_page > 1"
                    class="mt-10 flex items-center justify-between gap-4 border-t border-[#90b4ce]/30 pt-6"
                    aria-label="图库分页"
                >
                    <Link
                        v-if="gallery.photos.links.prev"
                        :href="gallery.photos.links.prev"
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
                        第 {{ gallery.photos.meta.current_page }} /
                        {{ gallery.photos.meta.last_page }} 页
                    </p>

                    <Link
                        v-if="gallery.photos.links.next"
                        :href="gallery.photos.links.next"
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
