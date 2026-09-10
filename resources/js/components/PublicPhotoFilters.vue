<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

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

const filtersOpen = ref(false);

const form = reactive({
    q: props.filters.q,
    categories: Object.fromEntries(
        Object.entries(props.filters.categories).map(([slug, id]) => [
            slug,
            String(id),
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
        source_mode:
            form.source_mode !== 'all'
                ? form.source_mode
                : undefined,
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
    router.get(props.submitPath, queryParams(), {
        preserveScroll: false,
        preserveState: false,
        replace: true,
    });
    filtersOpen.value = false;
};

const resetFilters = () => {
    router.get(
        props.submitPath,
        {},
        {
            preserveScroll: false,
            preserveState: false,
            replace: true,
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
    <div
        class="mb-5 flex flex-wrap items-center justify-between gap-3 lg:hidden"
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
        class="mb-5 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm lg:block lg:p-5"
        :class="filtersOpen ? 'block' : 'hidden'"
        @submit.prevent="applyFilters"
    >
        <div class="grid gap-4 lg:grid-cols-12">
            <label class="grid gap-2 lg:col-span-3">
                <span class="text-sm font-semibold">关键词</span>
                <input
                    v-model="form.q"
                    type="search"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                    placeholder="标题或说明"
                />
            </label>

            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">相册</span>
                <select
                    v-model="form.album_id"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                >
                    <option value="">全部相册</option>
                    <option
                        v-for="album in filterOptions.albums"
                        :key="album.id"
                        :value="String(album.id)"
                    >
                        {{ album.title }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">来源状态</span>
                <select
                    v-model="form.source_mode"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                >
                    <option
                        v-for="option in filterOptions.source_modes"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>


            <label class="grid gap-2 lg:col-span-3">
                <span class="text-sm font-semibold">排序</span>
                <select
                    v-model="form.sort"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                >
                    <option
                        v-for="sort in filterOptions.sorts"
                        :key="sort.value"
                        :value="sort.value"
                    >
                        {{ sort.label }}
                    </option>
                </select>
            </label>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-12">
            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">版权状态</span>
                <select
                    v-model="form.copyright_status"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                >
                    <option value="">全部版权状态</option>
                    <option
                        v-for="option in filterOptions.copyright_statuses"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">清晰度</span>
                <select
                    v-model="form.resolution"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                >
                    <option
                        v-for="option in filterOptions.resolutions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">横竖图</span>
                <select
                    v-model="form.orientation"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                >
                    <option
                        v-for="option in filterOptions.orientations"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">水印状态</span>
                <select
                    v-model="form.watermark_status"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                >
                    <option value="">全部水印状态</option>
                    <option
                        v-for="option in filterOptions.watermark_statuses"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">开始日期</span>
                <input
                    v-model="form.date_from"
                    type="date"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                />
            </label>

            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-semibold">结束日期</span>
                <input
                    v-model="form.date_to"
                    type="date"
                    class="h-11 rounded-sm border border-[#90b4ce]/55 px-3 text-sm outline-none focus:border-[#3da9fc]"
                />
            </label>
        </div>

        <div
            v-if="filterOptions.category_groups.length"
            class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
        >
            <label
                v-for="group in filterOptions.category_groups"
                :key="group.slug"
                class="grid gap-2"
            >
                <span class="text-sm font-semibold">{{ group.name }}</span>
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

        <div v-if="filterOptions.people_tags.length" class="mt-5">
            <p class="mb-3 text-sm font-semibold">人物同框</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tag in filterOptions.people_tags"
                    :key="tag.id"
                    type="button"
                    class="min-h-11 rounded-full border px-4 py-2 text-sm transition"
                    :class="
                        isSelected(form.people_tags, tag.id)
                            ? 'border-[#094067] bg-[#094067] text-[#fffffe]'
                            : 'border-[#90b4ce]/55 bg-[#fffffe] text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]'
                    "
                    @click="toggleId(form.people_tags, tag.id)"
                >
                    {{ tag.name }}
                </button>
            </div>
        </div>

        <div v-if="filterOptions.tags.length" class="mt-5">
            <p class="mb-3 text-sm font-semibold">标签</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tag in filterOptions.tags"
                    :key="tag.id"
                    type="button"
                    class="min-h-11 rounded-full border px-4 py-2 text-sm transition"
                    :class="
                        isSelected(form.tags, tag.id)
                            ? 'border-[#094067] bg-[#094067] text-[#fffffe]'
                            : 'border-[#90b4ce]/55 bg-[#fffffe] text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]'
                    "
                    @click="toggleId(form.tags, tag.id)"
                >
                    {{ tag.name }}
                </button>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="h-11 rounded-sm bg-[#3da9fc] px-5 text-sm font-semibold text-[#fffffe] transition hover:bg-[#1e94e6]"
            >
                {{ submitLabel }}
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
        v-if="activeChips.length"
        class="mb-8 flex flex-wrap items-center gap-2 border-b border-[#90b4ce]/25 pb-5"
    >
        <span class="text-sm font-semibold text-[#5f6c7b]">已选条件</span>
        <button
            v-for="chip in activeChips"
            :key="chip.key"
            type="button"
            class="inline-flex min-h-9 items-center gap-2 rounded-full bg-[#d8eefe] px-3 py-1 text-sm font-medium text-[#094067] transition hover:bg-[#90b4ce]/40"
            @click="removeChip(chip)"
        >
            {{ chip.label }}
            <X class="h-3.5 w-3.5" aria-hidden="true" />
        </button>
    </div>
</template>
