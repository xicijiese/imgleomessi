<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import ArchivePhotoCard from '@/components/ArchivePhotoCard.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import PublicPhotoFilters from '@/components/PublicPhotoFilters.vue';
import SeoHead from '@/components/SeoHead.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { useGalleryScrollReveal } from '@/composables/useGalleryScrollReveal';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Image, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import '../../../css/archive-browser.css';

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
    gallery: GalleryPayload;
}>();

const browserRoot = ref<HTMLElement | null>(null);
const isLoading = ref(false);
useArchiveBrowserMotion(browserRoot);
useGalleryScrollReveal(browserRoot, () => props.gallery.photos.data);
const hasPhotos = computed(() => props.gallery.photos.data.length > 0);
</script>

<template>
    <SeoHead :seo="gallery.seo" />
    <main ref="browserRoot" class="archive-browser archive-gallery">
        <PublicHeader
            :navigation="gallery.navigation"
            :site="gallery.site"
            theme="afa"
        />
        <div class="browser-container">
            <div class="browser-heading">
                <div>
                    <h1>图库</h1>
                    <p>按时间、赛事与生涯阶段查阅影像。</p>
                </div>
            </div>
            <PublicPhotoFilters
                :filters="gallery.filters"
                :filter-options="gallery.filter_options"
                submit-path="/photos"
                submit-label="应用筛选"
                @loading="isLoading = $event"
            >
                <template #quick-actions>
                    <Link
                        href="/search/image"
                        class="browser-outline browser-gallery-image-search"
                    >
                        <Image :size="18" aria-hidden="true" />以图搜图
                    </Link>
                </template>
                <template #count
                    ><strong>{{ gallery.photos.meta.total }}</strong>
                    张公开图片<span
                        v-if="gallery.photos.meta.from !== null"
                        class="browser-range"
                        >当前 {{ gallery.photos.meta.from }}–{{
                            gallery.photos.meta.to
                        }}</span
                    ></template
                >
            </PublicPhotoFilters>
            <div
                v-if="isLoading"
                class="browser-photo-grid"
                aria-label="正在加载图片"
                aria-busy="true"
            >
                <div
                    v-for="index in 12"
                    :key="index"
                    class="browser-skeleton"
                    aria-hidden="true"
                >
                    <div class="browser-photo" />
                    <div />
                    <div />
                </div>
            </div>
            <section
                v-else-if="hasPhotos"
                class="browser-photo-grid"
                aria-label="图片列表"
            >
                <ArchivePhotoCard
                    v-for="photo in gallery.photos.data"
                    :key="photo.uuid"
                    :photo="photo"
                />
            </section>
            <section v-else class="browser-empty-state">
                <Search :size="44" aria-hidden="true" />
                <h2>没有找到公开图片</h2>
                <p>当前筛选条件下没有结果，可以清空筛选后重新浏览图库。</p>
                <div>
                    <Link href="/photos" class="browser-primary">清空筛选</Link
                    ><Link href="/search/image" class="browser-outline"
                        >以图搜图</Link
                    >
                </div>
            </section>
            <ArchivePagination
                v-if="!isLoading"
                :meta="gallery.photos.meta"
                :links="gallery.photos.links"
                label="图库分页"
            />
        </div>
    </main>
</template>
