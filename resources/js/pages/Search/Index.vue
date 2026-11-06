<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import ArchivePhotoCard from '@/components/ArchivePhotoCard.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import PublicPhotoFilters from '@/components/PublicPhotoFilters.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, Image, Search } from 'lucide-vue-next';
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

const browserRoot = ref<HTMLElement | null>(null);
const isLoading = ref(false);
useArchiveBrowserMotion(browserRoot);
const hasPhotos = computed(() => props.search.photos.data.length > 0);
const hasSearchIdeas = computed(
    () =>
        props.search_operations.recommendations.length > 0 ||
        props.search_operations.hot_terms.length > 0,
);
const showSearchIdeas = computed(
    () =>
        hasSearchIdeas.value &&
        (!props.search.filters.q.trim() ||
            props.search.photos.meta.total === 0),
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
</script>

<template>
    <Head title="搜索" />
    <main ref="browserRoot" class="archive-browser">
        <PublicHeader
            :navigation="search.navigation"
            :site="search.site"
            theme="afa"
        />
        <div class="browser-container">
            <div class="browser-heading">
                <div>
                    <h1 :aria-label="resultTitle">
                        {{
                            search.filters.q.trim()
                                ? '搜索结果'
                                : '搜索图片档案'
                        }}
                    </h1>
                    <p>按标题、说明与关联资料查找图片。</p>
                </div>
                <Link href="/search/image" class="browser-image-search"
                    ><Image
                        :size="20"
                        aria-hidden="true" />以图搜图<ArrowUpRight
                        :size="16"
                        aria-hidden="true"
                /></Link>
            </div>
            <PublicPhotoFilters
                :filters="search.filters"
                :filter-options="search.filter_options"
                submit-path="/search"
                submit-label="搜索"
                @loading="isLoading = $event"
            >
                <template #count
                    ><strong>{{ search.photos.meta.total }}</strong>
                    张匹配图片<span
                        v-if="search.photos.meta.from !== null"
                        class="browser-range"
                        >当前 {{ search.photos.meta.from }}–{{
                            search.photos.meta.to
                        }}</span
                    ></template
                >
                <section
                    v-if="showSearchIdeas"
                    class="browser-search-ideas"
                    aria-label="搜索建议"
                >
                    <div v-if="search_operations.recommendations.length">
                        <h2>推荐搜索</h2>
                        <div>
                            <Link
                                v-for="item in search_operations.recommendations"
                                :key="`recommendation-${item.term}`"
                                :href="item.url"
                                :title="item.description ?? undefined"
                                >{{ item.label }}</Link
                            >
                        </div>
                    </div>
                    <div v-if="search_operations.hot_terms.length">
                        <h2>热门搜索</h2>
                        <div>
                            <Link
                                v-for="item in search_operations.hot_terms"
                                :key="`hot-${item.term}`"
                                :href="item.url"
                                >{{ item.label }}</Link
                            >
                        </div>
                    </div>
                </section>
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
                    v-for="photo in search.photos.data"
                    :key="photo.uuid"
                    :photo="photo"
                />
            </section>
            <section v-else class="browser-empty-state">
                <Search :size="44" aria-hidden="true" />
                <h2>{{ emptyTitle }}</h2>
                <p>{{ emptyDescription }}</p>
                <div>
                    <Link href="/search" class="browser-primary">清空筛选</Link
                    ><Link href="/search/image" class="browser-outline"
                        >以图搜图</Link
                    >
                </div>
            </section>
            <ArchivePagination
                v-if="!isLoading"
                :meta="search.photos.meta"
                :links="search.photos.links"
                label="搜索结果分页"
            />
        </div>
    </main>
</template>
