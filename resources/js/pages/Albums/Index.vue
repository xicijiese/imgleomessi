<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ImageOff, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, onUnmounted, reactive, ref } from 'vue';
import '../../../css/archive-browser.css';
import './Albums.css';

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
const root = ref<HTMLElement | null>(null);
const filterDialog = ref<HTMLDialogElement | null>(null);
const loading = ref(false);
let dialogTween: gsap.core.Tween | undefined;
useArchiveBrowserMotion(root);
const openFilters = () => {
    filterDialog.value?.showModal();
    filtersOpen.value = true;
    if (
        filterDialog.value &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        dialogTween?.revert();
        dialogTween = gsap.fromTo(
            filterDialog.value,
            { y: 12, opacity: 0 },
            {
                y: 0,
                opacity: 1,
                duration: 0.2,
                clearProps: 'opacity,transform',
            },
        );
    }
};
const closeFilters = () => {
    filterDialog.value?.close();
    filtersOpen.value = false;
};
onUnmounted(() => dialogTween?.revert());
const imageFailures = ref<Record<number, boolean>>({});

const form = reactive({
    q: props.albumIndex.filters.q,
    categories: Object.fromEntries(
        props.albumIndex.filter_options.category_groups.map((group) => [
            group.slug,
            props.albumIndex.filters.categories[group.slug]
                ? String(props.albumIndex.filters.categories[group.slug])
                : '',
        ]),
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
    closeFilters();
    router.get('/albums', queryParams(), {
        preserveScroll: false,
        preserveState: false,
        replace: true,
        onStart: () => {
            loading.value = true;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
};

const resetFilters = () => {
    closeFilters();
    router.get(
        '/albums',
        {},
        {
            preserveScroll: false,
            preserveState: false,
            replace: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
};

const formatDate = (date: string | null) => date ?? '发布时间待补充';

const quickGroups = computed(() =>
    props.albumIndex.filter_options.category_groups.filter((group) =>
        ['career-stage', 'year'].includes(group.slug),
    ),
);
const chips = computed(() => [
    ...(props.albumIndex.filters.q
        ? [{ key: 'q', label: `关键词：${props.albumIndex.filters.q}` }]
        : []),
    ...Object.entries(props.albumIndex.filters.categories).map(([slug, id]) => {
        const group = props.albumIndex.filter_options.category_groups.find(
            (item) => item.slug === slug,
        );
        return {
            key: slug,
            label: `${group?.name ?? slug}：${group?.children.find((item) => item.id === id)?.name ?? id}`,
        };
    }),
]);
const removeChip = (key: string) => {
    if (key === 'q') form.q = '';
    else form.categories[key] = '';
    applyFilters();
};
</script>
<template>
    <SeoHead :seo="albumIndex.seo" />
    <main ref="root" class="archive-browser archive-albums">
        <PublicHeader
            :navigation="albumIndex.navigation"
            :site="albumIndex.site"
            theme="afa"
        />
        <div class="browser-container">
            <div class="browser-heading">
                <div>
                    <h1>相册</h1>
                    <p>按赛事、年份、球队和主题浏览已发布的梅西影像集合。</p>
                </div>
            </div>
            <form @submit.prevent="applyFilters">
                <div class="browser-quick-filters">
                    <label class="browser-keyword"
                        ><span>关键词</span>
                        <div class="browser-search-input">
                            <input
                                v-model="form.q"
                                type="search"
                                aria-label="关键词"
                                placeholder="标题、说明或 URL 标识"
                            /><button
                                class="browser-search-submit"
                                type="submit"
                            >
                                搜索
                            </button>
                        </div></label
                    ><label
                        v-for="group in quickGroups"
                        :key="group.slug"
                        class="browser-quick-category"
                        ><span>{{ group.name }}</span
                        ><select
                            v-model="form.categories[group.slug]"
                            :aria-label="group.name"
                        >
                            <option value="">全部{{ group.name }}</option>
                            <option
                                v-for="child in group.children"
                                :key="child.id"
                                :value="String(child.id)"
                            >
                                {{ child.name }}
                            </option>
                        </select></label
                    ><button
                        class="browser-filter-toggle browser-outline"
                        type="button"
                        :aria-expanded="filtersOpen"
                        @click="openFilters"
                    >
                        <SlidersHorizontal
                            :size="18"
                            aria-hidden="true"
                        />全部筛选</button
                    ><button
                        class="browser-apply-top browser-primary"
                        type="submit"
                    >
                        应用筛选
                    </button>
                </div>
                <dialog
                    ref="filterDialog"
                    class="browser-filter-dialog"
                    aria-label="相册筛选"
                    @close="filtersOpen = false"
                >
                    <div class="browser-dialog-heading">
                        <div>
                            <h2>筛选相册</h2>
                            <p>组合分类查找相册。</p>
                        </div>
                        <button
                            class="browser-close-filter"
                            type="button"
                            aria-label="关闭筛选"
                            @click="closeFilters"
                        >
                            <X :size="22" aria-hidden="true" />
                        </button>
                    </div>
                    <div class="browser-filter-scroll">
                        <div class="browser-advanced-fields">
                            <label
                                v-for="group in albumIndex.filter_options
                                    .category_groups"
                                :key="group.slug"
                                ><span>{{ group.name }}</span
                                ><select
                                    v-model="form.categories[group.slug]"
                                    :aria-label="group.name"
                                >
                                    <option value="">全部</option>
                                    <option
                                        v-for="child in group.children"
                                        :key="child.id"
                                        :value="String(child.id)"
                                    >
                                        {{ child.name }}
                                    </option>
                                </select></label
                            >
                        </div>
                    </div>
                    <div class="browser-filter-actions">
                        <button
                            class="browser-text-button"
                            type="button"
                            @click="resetFilters"
                        >
                            重置条件</button
                        ><button class="browser-primary" type="submit">
                            应用筛选
                        </button>
                    </div>
                </dialog>
            </form>
            <div v-if="chips.length" class="browser-active-chips">
                <button
                    v-for="chip in chips"
                    :key="chip.key"
                    class="browser-chip"
                    type="button"
                    :aria-label="`移除${chip.label}`"
                    @click="removeChip(chip.key)"
                >
                    {{ chip.label }} <span aria-hidden="true">×</span></button
                ><button
                    class="browser-text-button"
                    type="button"
                    @click="resetFilters"
                >
                    清空全部
                </button>
            </div>
            <div class="browser-results-toolbar">
                <p v-if="loading" role="status">正在加载相册…</p>
                <p v-else>
                    <strong>{{ albumIndex.albums.meta.total }}</strong>
                    个公开相册
                    <span
                        v-if="albumIndex.albums.meta.from !== null"
                        class="browser-range"
                        >当前 {{ albumIndex.albums.meta.from }}–{{
                            albumIndex.albums.meta.to
                        }}</span
                    >
                </p>
                <label class="browser-sort-control"
                    >排序<select v-model="form.sort" aria-label="相册排序">
                        <option
                            v-for="sort in albumIndex.filter_options.sorts"
                            :key="sort.value"
                            :value="sort.value"
                        >
                            {{ sort.label }}
                        </option>
                    </select></label
                >
            </div>
            <div
                v-if="loading"
                class="album-grid"
                aria-label="正在加载相册"
                aria-busy="true"
            >
                <div v-for="n in 6" :key="n" class="browser-skeleton">
                    <div class="browser-photo"></div>
                    <div></div>
                    <div></div>
                </div>
            </div>
            <div v-else-if="hasAlbums" class="album-grid">
                <Link
                    v-for="album in albumIndex.albums.data"
                    :key="album.slug"
                    :href="album.url"
                    class="album-card"
                    :aria-label="`查看相册：${album.title}`"
                    ><div class="album-cover">
                        <img
                            v-if="
                                album.cover_image_url &&
                                !imageFailures[album.id]
                            "
                            :src="album.cover_image_url"
                            :alt="album.cover_alt"
                            loading="lazy"
                            @error="imageFailures[album.id] = true"
                        />
                        <div
                            v-else
                            class="browser-image-error"
                            role="img"
                            :aria-label="album.cover_alt + '，封面暂不可用'"
                        >
                            <ImageOff :size="28" aria-hidden="true" />
                        </div>
                        <span class="album-count"
                            >{{ album.public_photos_count }} 张</span
                        >
                    </div>
                    <div class="album-card-copy">
                        <h2>{{ album.title }}</h2>
                        <time>{{ formatDate(album.published_at) }}</time>
                        <p v-if="album.category_summary" class="album-category">
                            {{ album.category_summary }}
                        </p>
                    </div>
                    <p v-if="album.description" class="album-description">
                        {{ album.description }}
                    </p></Link
                >
            </div>
            <section v-else class="browser-empty-state">
                <h2>{{ emptyTitle }}</h2>
                <p>{{ emptyDescription }}</p>
                <div v-if="hasActiveFilters">
                    <button
                        class="browser-primary"
                        type="button"
                        @click="resetFilters"
                    >
                        清空全部
                    </button>
                </div>
            </section>
            <ArchivePagination
                v-if="!loading"
                :meta="albumIndex.albums.meta"
                :links="albumIndex.albums.links"
                label="相册分页"
            />
            <button
                v-if="hasActiveFilters && !chips.length"
                class="browser-text-button mt-4"
                type="button"
                @click="resetFilters"
            >
                清空全部
            </button>
        </div>
    </main>
</template>
