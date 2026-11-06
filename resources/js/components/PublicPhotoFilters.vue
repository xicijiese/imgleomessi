<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { Search, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, onUnmounted, reactive, ref, useId } from 'vue';

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

interface PublicPhotoFiltersPayload {
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
}

interface PublicPhotoFilterOptions {
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
}

interface ActiveChip {
    key: string;
    label: string;
    reset: () => void;
}

const props = defineProps<{
    filters: PublicPhotoFiltersPayload;
    filterOptions: PublicPhotoFilterOptions;
    submitPath: string;
    submitLabel: string;
}>();

const emit = defineEmits<{ loading: [value: boolean] }>();
const filtersOpen = ref(false);
const dialog = ref<HTMLDialogElement | null>(null);
const panelTitleId = useId();
const isApplying = ref(false);
let panelMotion: gsap.core.Tween | undefined;
const quickGroups = computed(() =>
    props.filterOptions.category_groups.filter((group) =>
        ['生涯阶段', '年份'].includes(group.name),
    ),
);
const detailSelects = computed(
    () =>
        [
            {
                key: 'source_mode',
                label: '来源状态',
                options: props.filterOptions.source_modes,
            },
            {
                key: 'copyright_status',
                label: '版权状态',
                options: props.filterOptions.copyright_statuses,
            },
            {
                key: 'resolution',
                label: '清晰度',
                options: props.filterOptions.resolutions,
            },
            {
                key: 'orientation',
                label: '横竖图',
                options: props.filterOptions.orientations,
            },
            {
                key: 'watermark_status',
                label: '水印状态',
                options: props.filterOptions.watermark_statuses,
            },
        ] as const,
);

const openFilters = () => {
    dialog.value?.showModal();
    filtersOpen.value = true;
    panelMotion?.revert();
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        panelMotion = gsap.fromTo(
            dialog.value,
            { y: 12 },
            {
                y: 0,
                duration: 0.2,
                ease: 'power2.out',
                clearProps: 'transform',
            },
        );
    }
};
const closeFilters = () => {
    dialog.value?.close();
    filtersOpen.value = false;
};
const closeBackdrop = (event: MouseEvent) => {
    if (event.target !== dialog.value || !dialog.value) return;
    const rect = dialog.value.getBoundingClientRect();
    if (
        event.clientX < rect.left ||
        event.clientX > rect.right ||
        event.clientY < rect.top ||
        event.clientY > rect.bottom
    )
        closeFilters();
};
const beginRequest = () => {
    isApplying.value = true;
    emit('loading', true);
};
const finishRequest = () => {
    isApplying.value = false;
    emit('loading', false);
};
onUnmounted(() => panelMotion?.revert());

const form = reactive({
    q: props.filters.q,
    categories: Object.fromEntries(
        props.filterOptions.category_groups.map((group) => [
            group.slug,
            props.filters.categories[group.slug]
                ? String(props.filters.categories[group.slug])
                : '',
        ]),
    ) as Record<string, string>,
    tags: props.filters.tags.map((id) => String(id)),
    people_tags: props.filters.people_tags.map((id) => String(id)),
    album_id: props.filters.album_id ? String(props.filters.album_id) : '',
    source_mode: props.filters.source_mode,
    copyright_status: props.filters.copyright_status ?? '',
    orientation: props.filters.orientation,
    resolution: props.filters.resolution,
    watermark_status: props.filters.watermark_status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    sort: props.filters.sort,
});

const selectedSourceMode = computed(() => form.source_mode);

const hasActiveFilters = computed(
    () =>
        Boolean(form.q.trim()) ||
        Object.values(form.categories).some(Boolean) ||
        form.tags.length > 0 ||
        form.people_tags.length > 0 ||
        Boolean(form.album_id) ||
        selectedSourceMode.value !== 'all' ||
        Boolean(form.copyright_status) ||
        form.orientation !== 'all' ||
        form.resolution !== 'all' ||
        Boolean(form.watermark_status) ||
        Boolean(form.date_from) ||
        Boolean(form.date_to) ||
        form.sort !== 'published_desc',
);

const queryParams = () => {
    const categories = Object.fromEntries(
        Object.entries(form.categories).filter(([, value]) => value !== ''),
    );

    return {
        q: form.q.trim() || undefined,
        categories: Object.keys(categories).length > 0 ? categories : undefined,
        tags: form.tags.length > 0 ? form.tags : undefined,
        people_tags: form.people_tags.length > 0 ? form.people_tags : undefined,
        album_id: form.album_id || undefined,
        source_mode: form.source_mode !== 'all' ? form.source_mode : undefined,
        copyright_status: form.copyright_status || undefined,
        orientation: form.orientation !== 'all' ? form.orientation : undefined,
        resolution: form.resolution !== 'all' ? form.resolution : undefined,
        watermark_status: form.watermark_status || undefined,
        date_from: form.date_from || undefined,
        date_to: form.date_to || undefined,
        sort: form.sort !== 'published_desc' ? form.sort : undefined,
    };
};

const applyFilters = () => {
    closeFilters();
    router.get(props.submitPath, queryParams(), {
        preserveScroll: false,
        preserveState: false,
        replace: true,
        onStart: beginRequest,
        onFinish: finishRequest,
    });
    filtersOpen.value = false;
};

const resetFilters = () => {
    closeFilters();
    router.get(
        props.submitPath,
        {},
        {
            preserveScroll: false,
            preserveState: false,
            replace: true,
            onStart: beginRequest,
            onFinish: finishRequest,
        },
    );
    filtersOpen.value = false;
};

const toggleId = (items: string[], id: number) => {
    const value = String(id);
    const index = items.indexOf(value);

    if (index === -1) {
        items.push(value);
        return;
    }

    items.splice(index, 1);
};

const isSelected = (items: string[], id: number) => items.includes(String(id));
const optionLabel = (options: SelectOption[], value: string) =>
    options.find((option) => option.value === value)?.label ?? value;
const categoryLabel = (slug: string, value: string) => {
    const group = props.filterOptions.category_groups.find(
        (item) => item.slug === slug,
    );
    const child = group?.children.find((item) => String(item.id) === value);

    return child && group ? `${group.name}：${child.name}` : value;
};
const albumLabel = (value: string) =>
    props.filterOptions.albums.find((item) => String(item.id) === value)
        ?.title ?? value;
const tagLabel = (options: TagOption[], value: string) =>
    options.find((item) => String(item.id) === value)?.name ?? value;

const activeChips = computed<ActiveChip[]>(() => {
    const chips: ActiveChip[] = [];

    if (form.q.trim()) {
        chips.push({
            key: 'q',
            label: `关键词：${form.q.trim()}`,
            reset: () => {
                form.q = '';
            },
        });
    }

    Object.entries(form.categories).forEach(([slug, value]) => {
        if (!value) {
            return;
        }

        chips.push({
            key: `category-${slug}`,
            label: categoryLabel(slug, value),
            reset: () => {
                form.categories[slug] = '';
            },
        });
    });

    form.tags.forEach((value) => {
        chips.push({
            key: `tag-${value}`,
            label: `标签：${tagLabel(props.filterOptions.tags, value)}`,
            reset: () => {
                form.tags = form.tags.filter((id) => id !== value);
            },
        });
    });

    form.people_tags.forEach((value) => {
        chips.push({
            key: `people-${value}`,
            label: `人物同框：${tagLabel(props.filterOptions.people_tags, value)}`,
            reset: () => {
                form.people_tags = form.people_tags.filter(
                    (id) => id !== value,
                );
            },
        });
    });

    if (form.album_id) {
        chips.push({
            key: 'album',
            label: `相册：${albumLabel(form.album_id)}`,
            reset: () => {
                form.album_id = '';
            },
        });
    }

    if (form.source_mode !== 'all') {
        chips.push({
            key: 'source',
            label: `来源：${optionLabel(props.filterOptions.source_modes, form.source_mode)}`,
            reset: () => {
                form.source_mode = 'all';
            },
        });
    }
    if (form.copyright_status) {
        chips.push({
            key: 'copyright',
            label: `版权：${optionLabel(props.filterOptions.copyright_statuses, form.copyright_status)}`,
            reset: () => {
                form.copyright_status = '';
            },
        });
    }

    if (form.orientation !== 'all') {
        chips.push({
            key: 'orientation',
            label: `构图：${optionLabel(props.filterOptions.orientations, form.orientation)}`,
            reset: () => {
                form.orientation = 'all';
            },
        });
    }

    if (form.resolution !== 'all') {
        chips.push({
            key: 'resolution',
            label: `清晰度：${optionLabel(props.filterOptions.resolutions, form.resolution)}`,
            reset: () => {
                form.resolution = 'all';
            },
        });
    }

    if (form.watermark_status) {
        chips.push({
            key: 'watermark',
            label: `水印：${optionLabel(props.filterOptions.watermark_statuses, form.watermark_status)}`,
            reset: () => {
                form.watermark_status = '';
            },
        });
    }

    if (form.date_from) {
        chips.push({
            key: 'date-from',
            label: `开始：${form.date_from}`,
            reset: () => {
                form.date_from = '';
            },
        });
    }

    if (form.date_to) {
        chips.push({
            key: 'date-to',
            label: `结束：${form.date_to}`,
            reset: () => {
                form.date_to = '';
            },
        });
    }

    if (form.sort !== 'published_desc') {
        chips.push({
            key: 'sort',
            label: `排序：${optionLabel(props.filterOptions.sorts, form.sort)}`,
            reset: () => {
                form.sort = 'published_desc';
            },
        });
    }

    return chips;
});

const removeChip = (chip: ActiveChip) => {
    chip.reset();
    applyFilters();
};
</script>

<template>
    <form
        class="browser-filters"
        :aria-busy="isApplying"
        @submit.prevent="applyFilters"
    >
        <div class="browser-quick-filters">
            <label class="browser-keyword"
                ><span>关键词</span>
                <div class="browser-search-input">
                    <Search :size="20" aria-hidden="true" /><input
                        v-model="form.q"
                        type="search"
                        placeholder="搜索标题、赛事、相册或人物"
                        aria-label="关键词"
                    /><button
                        class="browser-search-submit"
                        type="submit"
                        :disabled="isApplying"
                    >
                        搜索
                    </button>
                </div></label
            >
            <label
                v-for="group in quickGroups"
                :key="group.slug"
                class="browser-quick-category"
                ><span>{{ group.name }}</span
                ><select
                    v-model="form.categories[group.slug]"
                    :aria-label="group.name"
                >
                    <option value="">
                        全部{{ group.name === '年份' ? '年份' : '阶段' }}
                    </option>
                    <option
                        v-for="child in group.children"
                        :key="child.id"
                        :value="String(child.id)"
                    >
                        {{ child.name }}
                    </option>
                </select></label
            >
            <button
                type="button"
                class="browser-filter-toggle browser-outline"
                aria-haspopup="dialog"
                :aria-expanded="filtersOpen"
                @click="openFilters"
            >
                <SlidersHorizontal :size="18" aria-hidden="true" /><span
                    class="browser-desktop-filter-label"
                    >全部筛选</span
                ><span class="browser-mobile-filter-label">筛选</span>
            </button>
            <button
                type="submit"
                class="browser-apply-top browser-primary"
                :disabled="isApplying"
            >
                {{ isApplying ? '正在加载…' : submitLabel }}
            </button>
        </div>
        <dialog
            ref="dialog"
            class="browser-filter-dialog"
            :aria-labelledby="panelTitleId"
            @close="filtersOpen = false"
            @click="closeBackdrop"
        >
            <div class="browser-dialog-heading">
                <div>
                    <h2 :id="panelTitleId">筛选图片</h2>
                    <p>组合时间、赛事和图片资料，缩小查找范围。</p>
                </div>
                <button
                    type="button"
                    class="browser-close-filter"
                    aria-label="关闭筛选"
                    @click="closeFilters"
                >
                    <X :size="24" aria-hidden="true" />
                </button>
            </div>
            <div class="browser-filter-scroll">
                <div class="browser-advanced-fields">
                    <label
                        v-for="group in filterOptions.category_groups"
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
                    <label
                        ><span>相册</span
                        ><select v-model="form.album_id" aria-label="相册">
                            <option value="">全部相册</option>
                            <option
                                v-for="album in filterOptions.albums"
                                :key="album.id"
                                :value="String(album.id)"
                            >
                                {{ album.title }}
                            </option>
                        </select></label
                    >
                    <label v-for="field in detailSelects" :key="field.key"
                        ><span>{{ field.label }}</span
                        ><select
                            v-model="form[field.key]"
                            :aria-label="field.label"
                        >
                            <option
                                v-if="
                                    [
                                        'copyright_status',
                                        'watermark_status',
                                    ].includes(field.key)
                                "
                                value=""
                            >
                                全部{{ field.label }}
                            </option>
                            <option
                                v-for="option in field.options"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select></label
                    >
                    <label
                        ><span>开始日期</span
                        ><input v-model="form.date_from" type="date" /></label
                    ><label
                        ><span>结束日期</span
                        ><input v-model="form.date_to" type="date"
                    /></label>
                </div>
                <fieldset
                    v-if="filterOptions.people_tags.length"
                    class="browser-filter-tags"
                >
                    <legend>人物同框</legend>
                    <div>
                        <button
                            v-for="tag in filterOptions.people_tags"
                            :key="tag.id"
                            type="button"
                            class="browser-tag-choice"
                            :class="{
                                'is-selected': isSelected(
                                    form.people_tags,
                                    tag.id,
                                ),
                            }"
                            :aria-pressed="isSelected(form.people_tags, tag.id)"
                            @click="toggleId(form.people_tags, tag.id)"
                        >
                            {{ tag.name }}
                        </button>
                    </div>
                </fieldset>
                <fieldset
                    v-if="filterOptions.tags.length"
                    class="browser-filter-tags"
                >
                    <legend>标签</legend>
                    <div>
                        <button
                            v-for="tag in filterOptions.tags"
                            :key="tag.id"
                            type="button"
                            class="browser-tag-choice"
                            :class="{
                                'is-selected': isSelected(form.tags, tag.id),
                            }"
                            :aria-pressed="isSelected(form.tags, tag.id)"
                            @click="toggleId(form.tags, tag.id)"
                        >
                            {{ tag.name }}
                        </button>
                    </div>
                </fieldset>
            </div>
            <div class="browser-filter-actions">
                <button
                    type="button"
                    class="browser-text-button"
                    :disabled="isApplying || !hasActiveFilters"
                    @click="resetFilters"
                >
                    清空全部</button
                ><button
                    type="submit"
                    class="browser-primary"
                    :disabled="isApplying"
                >
                    {{ isApplying ? '正在加载…' : submitLabel }}
                </button>
            </div>
        </dialog>
        <div
            v-if="activeChips.length"
            class="browser-active-chips"
            aria-label="已选条件"
        >
            <button
                v-for="chip in activeChips"
                :key="chip.key"
                type="button"
                class="browser-chip"
                :aria-label="`移除${chip.label}`"
                :disabled="isApplying"
                @click="removeChip(chip)"
            >
                {{ chip.label }}<X :size="13" aria-hidden="true" /></button
            ><button
                class="browser-text-button"
                type="button"
                :disabled="isApplying"
                @click="resetFilters"
            >
                清空全部
            </button>
        </div>
        <slot />
        <div class="browser-results-toolbar">
            <p aria-live="polite">
                <span v-if="isApplying">正在加载图片…</span
                ><slot v-else name="count" />
            </p>
            <label class="browser-sort-control"
                >排序<select
                    v-model="form.sort"
                    aria-label="排序"
                    :disabled="isApplying"
                    @change="applyFilters"
                >
                    <option
                        v-for="sort in filterOptions.sorts"
                        :key="sort.value"
                        :value="sort.value"
                    >
                        {{ sort.label }}
                    </option>
                </select></label
            >
        </div>
    </form>
</template>
