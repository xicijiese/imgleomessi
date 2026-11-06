<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';

import { computed, onBeforeUnmount, reactive, ref } from 'vue';

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
    closeFilters();
    router.get(props.archive.dimension.breadcrumbs[1].url, selectedQuery(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    closeFilters();
    form.q = '';
    form.sort = 'event_desc';
    form.years = [];
    form.tags = [];
    form.people_tags = [];
    router.get(
        props.archive.dimension.breadcrumbs[1].url,
        {},
        { preserveScroll: true, replace: true },
    );
};

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(pageRoot);
const filterDialog = ref<HTMLDialogElement | null>(null);
const filterTrigger = ref<HTMLButtonElement | null>(null);
const filterOpen = ref(false);
const closeFilters = () => filterDialog.value?.close();
const openFilters = () => {
    filterDialog.value?.showModal();
    filterOpen.value = true;
};
const filterClosed = () => {
    filterOpen.value = false;
    filterTrigger.value?.focus();
};
onBeforeUnmount(closeFilters);
</script>
<template>
    <SeoHead :seo="archive.seo" />
    <main ref="pageRoot" class="archive-browser archive-dimensions">
        <PublicHeader
            :site="archive.site"
            :navigation="archive.navigation"
            theme="afa"
        />
        <div class="dimension-main">
            <section class="intro browser-heading">
                <Link :href="archive.dimension.index_url" class="bread"
                    >← {{ archive.dimension.label }}</Link
                >
                <div class="title-row">
                    <div>
                        <h1>{{ archive.category.name }}</h1>
                        <p>
                            {{
                                archive.category.description ||
                                `浏览${archive.category.name}相关的公开图片、相册和时间线入口。`
                            }}
                        </p>
                        <div class="summary">
                            <span
                                ><strong>{{
                                    archive.summary.photos_count
                                }}</strong>
                                张公开图片</span
                            ><span
                                ><strong>{{
                                    archive.summary.albums_count
                                }}</strong>
                                个相册</span
                            ><span
                                ><strong>{{
                                    archive.summary.years_count
                                }}</strong>
                                个年份</span
                            >
                        </div>
                    </div>
                    <div class="entries">
                        <Link :href="archive.dimension.timeline_url"
                            >查看时间线</Link
                        ><Link :href="archive.dimension.gallery_url"
                            >返回图库</Link
                        >
                    </div>
                </div>
            </section>
            <form @submit.prevent="applyFilters">
                <div class="controls browser-quick-filters">
                    <label class="keyword"
                        >关键词<input
                            v-model="form.q"
                            type="search"
                            placeholder="标题或描述" /></label
                    ><label class="sort"
                        >排序<select v-model="form.sort">
                            <option
                                v-for="sort in archive.filter_options.sorts"
                                :key="sort.value"
                                :value="sort.value"
                            >
                                {{ sort.label }}
                            </option>
                        </select></label
                    ><button
                        ref="filterTrigger"
                        type="button"
                        class="outline"
                        aria-haspopup="dialog"
                        :aria-expanded="filterOpen"
                        @click="openFilters"
                    >
                        全部筛选</button
                    ><button class="primary" type="submit">应用筛选</button
                    ><button
                        class="outline"
                        type="button"
                        @click="resetFilters"
                    >
                        重置
                    </button>
                </div>
                <dialog
                    ref="filterDialog"
                    aria-label="档案筛选"
                    @close="filterClosed"
                >
                    <div class="dialog-heading">
                        <h2>筛选图片</h2>
                        <button
                            class="close-filter"
                            type="button"
                            aria-label="关闭筛选"
                            @click="closeFilters"
                        >
                            ×
                        </button>
                    </div>
                    <div class="filter-scroll">
                        <fieldset>
                            <legend>年份</legend>
                            <div class="checks">
                                <label
                                    v-for="item in archive.filter_options.years"
                                    :key="item.value"
                                    ><input
                                        v-model="form.years"
                                        type="checkbox"
                                        :value="item.value.toString()"
                                    />{{ item.label }}</label
                                >
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>标签</legend>
                            <div class="checks">
                                <label
                                    v-for="item in archive.filter_options.tags.slice(
                                        0,
                                        14,
                                    )"
                                    :key="item.id"
                                    ><input
                                        v-model="form.tags"
                                        type="checkbox"
                                        :value="item.id.toString()"
                                    />{{ item.name }}</label
                                >
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>人物</legend>
                            <div class="checks">
                                <label
                                    v-for="item in archive.filter_options.people_tags.slice(
                                        0,
                                        10,
                                    )"
                                    :key="item.id"
                                    ><input
                                        v-model="form.people_tags"
                                        type="checkbox"
                                        :value="item.id.toString()"
                                    />{{ item.name }}</label
                                >
                            </div>
                        </fieldset>
                    </div>
                    <div class="filter-actions">
                        <button
                            class="outline"
                            type="button"
                            @click="resetFilters"
                        >
                            重置条件</button
                        ><button class="primary" type="submit">应用筛选</button>
                    </div>
                </dialog>
            </form>
            <p class="result-line">
                共 {{ archive.photos.meta.total }} 张图片<span v-if="hasPhotos">
                    · 当前显示 {{ archive.photos.meta.from }}–{{
                        archive.photos.meta.to
                    }}
                    张</span
                >
            </p>
            <div v-if="hasPhotos" class="photo-grid">
                <article
                    v-for="photo in archive.photos.data"
                    :key="photo.uuid"
                    class="photo"
                >
                    <Link
                        :href="photo.url"
                        class="cover"
                        :aria-label="`查看图片：${photo.title}`"
                        ><img
                            v-if="
                                photo.image_url &&
                                !imageFailures[`photo-${photo.id}`]
                            "
                            :src="photo.image_url"
                            :alt="photo.alt"
                            loading="lazy"
                            @error="imageFailures[`photo-${photo.id}`] = true"
                        /><span v-else class="failed"
                            >图片暂时无法显示</span
                        ></Link
                    >
                    <h3>
                        <Link :href="photo.url">{{ photo.title }}</Link>
                    </h3>
                    <p>{{ formatDate(photo.event_date) }}</p>
                    <p v-if="photo.category_summary">
                        {{ photo.category_summary }}
                    </p>
                    <div class="tags">
                        <span
                            v-for="tag in photo.tags.slice(0, 3)"
                            :key="tag.id"
                            >{{ tag.name }}</span
                        >
                    </div>
                </article>
            </div>
            <section v-else class="empty">
                <h2>当前维度暂无公开图片</h2>
                <p>
                    聚合页只展示已发布、可公开展示的图片。可以调整筛选条件，或返回图库继续查找。
                </p>
            </section>
            <ArchivePagination
                :meta="archive.photos.meta"
                :links="archive.photos.links"
                label="聚合分页"
            />
            <section v-if="archive.albums.length" class="related">
                <h2>相关相册</h2>
                <div class="related-grid">
                    <Link
                        v-for="album in archive.albums"
                        :key="album.id"
                        :href="album.url"
                        class="related-album"
                        ><span class="cover"
                            ><img
                                v-if="
                                    album.cover_image_url &&
                                    !imageFailures[`album-${album.id}`]
                                "
                                :src="album.cover_image_url"
                                :alt="album.cover_alt"
                                loading="lazy"
                                @error="
                                    imageFailures[`album-${album.id}`] = true
                                "
                            /><span v-else class="failed"
                                >图片暂时无法显示</span
                            ></span
                        >
                        <div>
                            <h3>{{ album.title }}</h3>
                            <p>{{ album.public_photos_count }} 张公开图片</p>
                            <p v-if="album.category_summary">
                                {{ album.category_summary }}
                            </p>
                        </div></Link
                    >
                </div>
            </section>
            <section v-if="archive.related_topics.length" class="related">
                <h2>相关专题</h2>
                <div class="related-grid">
                    <Link
                        v-for="topic in archive.related_topics"
                        :key="topic.url"
                        :href="topic.url"
                        class="text-topic"
                        ><h3>{{ topic.title }}</h3>
                        <p v-if="topic.description">
                            {{ topic.description }}
                        </p></Link
                    >
                </div>
            </section>
        </div>
    </main>
</template>
<style scoped src="./Dimensions.css"></style>
