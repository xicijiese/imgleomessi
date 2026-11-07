<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import SharePanel from '@/components/SharePanel.vue';
import { useGalleryScrollReveal } from '@/composables/useGalleryScrollReveal';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ArrowLeft, ChevronDown, ImageOff } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import '../../../css/archive-browser.css';
import './Albums.css';

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

const root = ref<HTMLElement | null>(null);
useGalleryScrollReveal(
    root,
    () => props.albumDetail.photos.data,
    '.browser-photo-grid > *',
);
const isMobile = ref(false);
const infoExpanded = ref(false);
const infoVisible = computed(() => !isMobile.value || infoExpanded.value);
let mobileQuery: MediaQueryList | undefined;
let motion: gsap.MatchMedia | undefined;
const syncMobile = () => {
    isMobile.value = mobileQuery?.matches ?? false;
};
onMounted(() => {
    mobileQuery = window.matchMedia('(max-width: 767px)');
    syncMobile();
    mobileQuery.addEventListener('change', syncMobile);
    motion = gsap.matchMedia();
    motion.add(
        '(prefers-reduced-motion: no-preference)',
        () => {
            gsap.from('.album-intro', {
                y: 6,
                duration: 0.24,
                clearProps: 'transform',
            });
        },
        root.value ?? undefined,
    );
});
onUnmounted(() => {
    mobileQuery?.removeEventListener('change', syncMobile);
    motion?.revert();
});
watch(
    () => props.albumDetail.album.slug,
    () => {
        infoExpanded.value = false;
        coverFailed.value = false;
        imageFailures.value = {};
    },
);
</script>
<template>
    <SeoHead :seo="albumDetail.seo" />
    <main ref="root" class="archive-browser archive-albums">
        <PublicHeader
            :navigation="albumDetail.navigation"
            :site="albumDetail.site"
            theme="afa"
        />
        <div class="browser-container">
            <Link href="/albums" class="album-back"
                ><ArrowLeft :size="18" aria-hidden="true" />返回相册</Link
            >
            <div class="album-intro">
                <div class="album-cover">
                    <img
                        v-if="albumDetail.album.cover_image_url && !coverFailed"
                        :src="albumDetail.album.cover_image_url"
                        :alt="albumDetail.album.cover_alt"
                        @error="coverFailed = true"
                    />
                    <div
                        v-else
                        class="browser-image-error"
                        role="img"
                        :aria-label="
                            albumDetail.album.cover_alt + '，封面暂不可用'
                        "
                    >
                        <ImageOff :size="28" aria-hidden="true" /><span
                            >封面暂时无法显示</span
                        >
                    </div>
                </div>
                <div class="album-intro-copy">
                    <div class="album-title-row">
                        <h1>{{ albumDetail.album.title }}</h1>
                        <SharePanel
                            type="album"
                            :target-key="albumDetail.album.slug"
                            :title="albumDetail.album.title"
                            theme="archive"
                        />
                    </div>
                    <p class="album-summary">
                        {{ albumDetail.album.public_photos_count }} 张公开图片 ·
                        发布于 {{ formatDate(albumDetail.album.published_at) }}
                    </p>
                    <button
                        type="button"
                        class="album-info-toggle"
                        :aria-expanded="infoVisible"
                        aria-controls="album-info"
                        @click="infoExpanded = !infoExpanded"
                    >
                        <strong>相册资料</strong
                        ><span>{{ infoVisible ? '收起' : '展开' }}</span
                        ><ChevronDown :size="18" aria-hidden="true" />
                    </button>
                    <div id="album-info" v-show="infoVisible">
                        <p
                            v-if="albumDetail.album.description"
                            class="album-description"
                        >
                            {{ albumDetail.album.description }}
                        </p>
                        <div
                            v-if="albumDetail.album.categories.length"
                            class="album-categories"
                        >
                            <span
                                v-for="category in albumDetail.album.categories"
                                :key="category.id"
                                >{{ category.root_name }} /
                                {{ category.name }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
            <div class="album-photo-toolbar">
                <div>
                    <h2>相册图片</h2>
                    <p>
                        共 {{ albumDetail.photos.meta.total }} 张公开图片<span
                            v-if="albumDetail.photos.meta.from !== null"
                            >，当前 {{ albumDetail.photos.meta.from }}–{{
                                albumDetail.photos.meta.to
                            }}</span
                        >
                    </p>
                </div>
                <form @submit.prevent="applySort">
                    <label class="browser-sort-control"
                        >排序<select
                            v-model="form.sort"
                            aria-label="相册图片排序"
                            @change="applySort"
                        >
                            <option
                                v-for="sort in albumDetail.filter_options.sorts"
                                :key="sort.value"
                                :value="sort.value"
                            >
                                {{ sort.label }}
                            </option>
                        </select></label
                    >
                </form>
            </div>
            <div class="browser-photo-grid">
                <Link
                    v-for="photo in albumDetail.photos.data"
                    :key="photo.uuid"
                    :href="photo.url"
                    class="browser-photo-card"
                    :aria-label="`查看图片：${photo.title}`"
                    ><div class="browser-photo">
                        <img
                            v-if="photo.image_url && !imageFailures[photo.id]"
                            :src="photo.image_url"
                            :alt="photo.alt"
                            loading="lazy"
                            @error="imageFailures[photo.id] = true"
                        />
                        <div
                            v-else
                            class="browser-image-error"
                            role="img"
                            :aria-label="photo.title + '，预览暂不可用'"
                        >
                            <ImageOff :size="28" aria-hidden="true" />
                        </div>
                        <span class="browser-entry-arrow" aria-hidden="true"
                            >↗</span
                        >
                    </div>
                    <div class="browser-card-copy">
                        <h3>{{ photo.title }}</h3>
                        <p class="browser-date">
                            {{ formatDate(photo.event_date) }}
                        </p>
                        <p
                            v-if="photo.category_summary"
                            class="browser-category"
                        >
                            {{ photo.category_summary }}
                        </p>
                        <div v-if="photo.tags.length" class="browser-tags">
                            <span
                                v-for="tag in photo.tags.slice(0, 3)"
                                :key="tag.id"
                                >{{ tag.name }}</span
                            >
                        </div>
                    </div></Link
                >
            </div>
            <ArchivePagination
                :meta="albumDetail.photos.meta"
                :links="albumDetail.photos.links"
                label="相册图片分页"
            />
        </div>
    </main>
</template>
