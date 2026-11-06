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

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
}

interface SelectOption {
    value: string;
    label: string;
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

interface TagPayload {
    id: number;
    name: string;
    type: string;
}

interface MonthSummary {
    month: number;
    label: string;
    url: string;
    photos_count: number;
    cover_image_url: string | null;
    cover_alt: string;
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

interface TimelineArchivePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    seo: SeoPayload;
    scope: {
        type: 'year' | 'month';
        title: string;
        subtitle: string;
        year: number;
        month: number | null;
        month_label?: string;
        breadcrumbs: BreadcrumbItem[];
    };
    summary: {
        photos_count: number;
        albums_count: number;
        months_count: number;
    };
    filters: {
        q: string;
        categories: Record<string, number>;
        tags: number[];
        sort: string;
    };
    filter_options: {
        category_groups: CategoryGroup[];
        tags: TagPayload[];
        sorts: SelectOption[];
    };
    months: MonthSummary[];
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
    timeline: TimelineArchivePayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});
const form = reactive({
    q: props.timeline.filters.q,
    sort: props.timeline.filters.sort,
    categories: Object.fromEntries(
        props.timeline.filter_options.category_groups.map((group) => [
            group.slug,
            props.timeline.filters.categories[group.slug]?.toString() ?? '',
        ]),
    ) as Record<string, string>,
    tags: props.timeline.filters.tags.map((id) => id.toString()),
});

const submitPath = computed(() => {
    const year = props.timeline.scope.year;
    const month = props.timeline.scope.month;

    return month === null
        ? `/timeline/${year}`
        : `/timeline/${year}/${month.toString().padStart(2, '0')}`;
});
const hasPhotos = computed(() => props.timeline.photos.data.length > 0);
const visibleMonths = computed(() => props.timeline.months.slice(0, 12));
const formatDate = (date: string | null) => date ?? '日期待补充';

const selectedQuery = () => {
    const categories = Object.fromEntries(
        Object.entries(form.categories).filter((entry) => entry[1] !== ''),
    );

    return {
        q: form.q.trim() || undefined,
        sort: form.sort === 'event_desc' ? undefined : form.sort,
        categories: Object.keys(categories).length ? categories : undefined,
        tags: form.tags.length ? form.tags : undefined,
    };
};

const applyFilters = () => {
    closeFilters();
    router.get(submitPath.value, selectedQuery(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    closeFilters();
    form.q = '';
    form.sort = 'event_desc';
    Object.keys(form.categories).forEach((key) => {
        form.categories[key] = '';
    });
    form.tags = [];
    router.get(submitPath.value, {}, { preserveScroll: true, replace: true });
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
    <SeoHead :seo="timeline.seo" />
    <main ref="pageRoot" class="archive-browser archive-timeline">
        <PublicHeader
            :site="timeline.site"
            :navigation="timeline.navigation"
            theme="afa"
        />
        <div class="timeline-main">
            <section class="intro browser-heading">
                <nav class="bread" aria-label="面包屑">
                    <template
                        v-for="(item, index) in timeline.scope.breadcrumbs"
                        :key="item.url"
                        ><span v-if="index" aria-hidden="true">/</span
                        ><Link :href="item.url">{{
                            item.label
                        }}</Link></template
                    >
                </nav>
                <h1>{{ timeline.scope.title }}</h1>
                <p>{{ timeline.scope.subtitle }}</p>
                <div class="summary">
                    <span
                        ><strong>{{ timeline.summary.photos_count }}</strong>
                        张图片</span
                    ><span
                        ><strong>{{ timeline.summary.albums_count }}</strong>
                        个相册</span
                    ><span
                        ><strong>{{ timeline.summary.months_count }}</strong>
                        个月份</span
                    >
                </div>
            </section>
            <nav
                v-if="visibleMonths.length"
                class="months"
                aria-label="月份入口"
            >
                <Link
                    v-for="month in visibleMonths"
                    :key="month.month"
                    :href="month.url"
                    :aria-current="
                        timeline.scope.month === month.month
                            ? 'page'
                            : undefined
                    "
                    >{{ month.label }}
                    <small>{{ month.photos_count }} 张</small></Link
                >
            </nav>
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
                                v-for="sort in timeline.filter_options.sorts"
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
                    aria-label="时间线筛选"
                    @close="filterClosed"
                >
                    <div class="dialog-heading">
                        <h2>筛选时间线图片</h2>
                        <button
                            type="button"
                            class="close-filter"
                            aria-label="关闭筛选"
                            @click="closeFilters"
                        >
                            ×
                        </button>
                    </div>
                    <div class="filter-scroll">
                        <div class="category-fields">
                            <label
                                v-for="group in timeline.filter_options
                                    .category_groups"
                                :key="group.id"
                                >{{ group.name
                                }}<select v-model="form.categories[group.slug]">
                                    <option value="">全部</option>
                                    <option
                                        v-for="child in group.children"
                                        :key="child.id"
                                        :value="child.id.toString()"
                                    >
                                        {{ child.name }}
                                    </option>
                                </select></label
                            >
                        </div>
                        <fieldset v-if="timeline.filter_options.tags.length">
                            <legend>标签</legend>
                            <div class="checks">
                                <label
                                    v-for="tag in timeline.filter_options.tags.slice(
                                        0,
                                        16,
                                    )"
                                    :key="tag.id"
                                    ><input
                                        v-model="form.tags"
                                        type="checkbox"
                                        :value="tag.id.toString()"
                                    />{{ tag.name }}</label
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
                共 {{ timeline.photos.meta.total }} 张图片<span
                    v-if="hasPhotos"
                >
                    · 当前显示 {{ timeline.photos.meta.from }}–{{
                        timeline.photos.meta.to
                    }}
                    张</span
                >
            </p>
            <div v-if="hasPhotos" class="photo-grid">
                <article
                    v-for="photo in timeline.photos.data"
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
                <h2>当前时间范围暂无公开图片</h2>
                <p>
                    时间轴只展示已发布、可公开展示并且填写了事件日期的图片。可以调整筛选条件或返回总时间线。
                </p>
            </section>
            <ArchivePagination
                :meta="timeline.photos.meta"
                :links="timeline.photos.links"
                label="聚合分页"
            />
            <section v-if="timeline.albums.length" class="related">
                <h2>相关相册</h2>
                <div class="related-grid">
                    <Link
                        v-for="album in timeline.albums"
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
            <section v-if="timeline.related_topics.length" class="related">
                <h2>相关专题</h2>
                <div class="related-grid">
                    <Link
                        v-for="topic in timeline.related_topics"
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
<style scoped src="./Timeline.css"></style>
