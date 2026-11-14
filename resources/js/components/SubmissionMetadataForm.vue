<script setup lang="ts">
import type {
    SubmissionDictionaries,
    SubmissionMetadata,
} from '@/types/submissions';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps<{
    modelValue: SubmissionMetadata;
    dictionaries: SubmissionDictionaries;
    idPrefix: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [SubmissionMetadata] }>();
const formRoot = ref<HTMLElement | null>(null);
function closeOtherMenus(event: PointerEvent) {
    formRoot.value
        ?.querySelectorAll<HTMLDetailsElement>('details[open]')
        .forEach((menu) => {
            if (!menu.contains(event.target as Node)) menu.open = false;
        });
}
onMounted(() => document.addEventListener('pointerdown', closeOtherMenus));
onBeforeUnmount(() =>
    document.removeEventListener('pointerdown', closeOtherMenus),
);
const tagsQuery = ref('');
const addedTags = ref<{ id: number; name: string }[]>([]);
const tagBusy = ref(false);
const tagError = ref('');
const availableTags = computed(() =>
    Array.from(
        new Map(
            [...props.dictionaries.tags, ...addedTags.value].map((tag) => [
                tag.id,
                tag,
            ]),
        ).values(),
    ),
);
const filteredTags = computed(() =>
    availableTags.value
        .filter(
            (tag) =>
                tag.name
                    .toLowerCase()
                    .includes(tagsQuery.value.trim().toLowerCase()) &&
                !props.modelValue.tag_ids.includes(tag.id),
        )
        .slice(0, 30),
);
function toggleTag(id: number) {
    change(
        'tag_ids',
        props.modelValue.tag_ids.includes(id)
            ? props.modelValue.tag_ids.filter((value) => value !== id)
            : [...props.modelValue.tag_ids, id],
    );
    tagsQuery.value = '';
}
async function createTag() {
    if (tagBusy.value || !tagsQuery.value.trim()) return;
    tagBusy.value = true;
    tagError.value = '';
    try {
        const response = await fetch('/me/submissions/tags', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((value) => value.startsWith('XSRF-TOKEN='))
                        ?.slice(11) ?? '',
                ),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ name: tagsQuery.value.trim() }),
        });
        if (response.status === 419)
            throw new Error(
                '页面凭证已过期，请刷新后重试；已填写的资料请先保留。',
            );
        const data = await response.json();
        if (!response.ok)
            throw new Error(
                Object.values(data.errors ?? {})
                    .flat()
                    .join('；') || '新增失败，请稍后重试。',
            );
        if (!availableTags.value.some((tag) => tag.id === data.id))
            addedTags.value.push(data);
        if (!props.modelValue.tag_ids.includes(data.id)) toggleTag(data.id);
        router.reload({ only: ['me'] });
    } catch (error) {
        tagError.value = error instanceof Error ? error.message : '新增失败';
    } finally {
        tagBusy.value = false;
    }
}
function change<K extends keyof SubmissionMetadata>(
    key: K,
    value: SubmissionMetadata[K],
) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
function changeCategory(ids: number[], id: number, multiple: boolean) {
    const selected = props.modelValue.category_ids.filter((value) =>
        ids.includes(value),
    );
    const values = selected.includes(id)
        ? selected.filter((value) => value !== id)
        : multiple
          ? [...selected, id]
          : [id];
    change('category_ids', [
        ...props.modelValue.category_ids.filter((id) => !ids.includes(id)),
        ...values,
    ]);
}
const inputClass =
    'mt-1 min-h-11 w-full rounded-sm border border-[#90b4ce] bg-white px-3 py-2 text-sm text-[#094067] focus:outline-2 focus:outline-[#3da9fc]';
</script>
<template>
    <div ref="formRoot" class="grid gap-4 sm:grid-cols-2">
        <label :for="`${idPrefix}-title`" class="text-sm"
            >标题<input
                :id="`${idPrefix}-title`"
                :class="inputClass"
                :value="modelValue.title"
                maxlength="255"
                @input="
                    change('title', ($event.target as HTMLInputElement).value)
                "
        /></label>
        <label :for="`${idPrefix}-date`" class="text-sm"
            >图片日期（不清楚可留空）<input
                :id="`${idPrefix}-date`"
                type="date"
                :class="inputClass"
                :value="modelValue.event_date"
                @input="
                    change(
                        'event_date',
                        ($event.target as HTMLInputElement).value,
                    )
                "
        /></label>
        <div
            v-for="category in dictionaries.categories"
            :key="category.id"
            class="min-w-0 text-sm"
        >
            <span
                >{{ category.name
                }}<span
                    v-if="category.required"
                    class="ml-1 text-red-600"
                    aria-label="必填"
                    >*</span
                ></span
            >
            <details
                class="relative"
                @keydown.esc="
                    ($event.currentTarget as HTMLDetailsElement).open = false
                "
            >
                <summary
                    :class="[
                        inputClass,
                        'flex cursor-pointer list-none items-center justify-between gap-2',
                    ]"
                    :aria-label="category.name"
                >
                    <span class="truncate">{{
                        category.children
                            .filter((child) =>
                                modelValue.category_ids.includes(child.id),
                            )
                            .map((child) => child.name)
                            .join('、') ||
                        (category.required
                            ? '请选择（必填，可从相册继承）'
                            : '不确定 / 暂不填写')
                    }}</span
                    ><span aria-hidden="true">⌄</span>
                </summary>
                <div
                    class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded border border-[#90b4ce] bg-white p-2 shadow-lg"
                >
                    <label
                        v-for="child in category.children"
                        :key="child.id"
                        class="flex min-h-10 cursor-pointer items-center gap-2 rounded px-2 hover:bg-[#edf7fd]"
                    >
                        <input
                            type="checkbox"
                            :checked="
                                modelValue.category_ids.includes(child.id)
                            "
                            @change="
                                changeCategory(
                                    category.children.map((value) => value.id),
                                    child.id,
                                    !!category.multiple,
                                )
                            "
                        />{{ child.name }}
                    </label>
                    <p class="px-2 pt-2 text-xs text-[#526779]">
                        {{
                            category.multiple
                                ? '可勾选多项，再次点击可取消'
                                : '请选择一项，再次点击可取消'
                        }}
                    </p>
                </div>
            </details>
        </div>
        <label class="text-sm"
            >公开相册<select
                :class="inputClass"
                :value="modelValue.album_id ?? ''"
                @change="
                    change(
                        'album_id',
                        ($event.target as HTMLSelectElement).value
                            ? Number(($event.target as HTMLSelectElement).value)
                            : null,
                    )
                "
            >
                <option value="">不归入相册</option>
                <option
                    v-for="album in dictionaries.albums"
                    :key="album.id"
                    :value="album.id"
                >
                    {{ album.title }}
                </option>
            </select></label
        >
        <div class="relative text-sm">
            <label :for="`${idPrefix}-tag-search`"
                >标签（搜索选择，可新增）</label
            >
            <div class="flex flex-wrap gap-2 py-2">
                <button
                    v-for="id in modelValue.tag_ids"
                    :key="id"
                    type="button"
                    class="rounded bg-[#edf7fd] px-2 py-1"
                    @click="toggleTag(id)"
                >
                    {{ availableTags.find((tag) => tag.id === id)?.name ?? id }}
                    ×
                </button>
            </div>
            <input
                :id="`${idPrefix}-tag-search`"
                v-model="tagsQuery"
                type="search"
                :class="inputClass"
                placeholder="输入标签关键词搜索"
                maxlength="50"
                @keydown.esc="tagsQuery = ''"
                @keydown.enter.prevent="
                    filteredTags.length
                        ? toggleTag(filteredTags[0].id)
                        : createTag()
                "
            />
            <div
                v-if="tagsQuery.trim()"
                class="absolute z-20 max-h-56 w-full overflow-y-auto rounded border border-[#cbd8e2] bg-white p-1 shadow-lg"
            >
                <button
                    v-for="tag in filteredTags"
                    :key="tag.id"
                    type="button"
                    class="block w-full rounded px-3 py-2 text-left hover:bg-[#edf7fd]"
                    @click="toggleTag(tag.id)"
                >
                    {{ tag.name }}
                </button>
                <button
                    v-if="
                        !availableTags.some(
                            (tag) => tag.name === tagsQuery.trim(),
                        )
                    "
                    type="button"
                    class="block w-full px-3 py-2 text-left font-semibold"
                    :disabled="tagBusy"
                    @click="createTag"
                >
                    {{ tagBusy ? '新增中…' : `新增标签“${tagsQuery.trim()}”` }}
                </button>
                <p v-if="!filteredTags.length" class="px-3 py-2 text-[#526779]">
                    没有匹配的候选标签
                </p>
            </div>
            <p v-if="tagError" role="alert" class="text-red-600">
                {{ tagError }}
            </p>
        </div>
        <label class="text-sm"
            >来源链接<input
                :class="inputClass"
                type="url"
                :value="modelValue.source_url"
                maxlength="2000"
                placeholder="https://"
                @input="
                    change(
                        'source_url',
                        ($event.target as HTMLInputElement).value,
                    )
                "
        /></label>
        <label class="text-sm"
            >来源说明（选填）<textarea
                :class="inputClass"
                rows="2"
                :value="modelValue.source_note"
                maxlength="2000"
                @input="
                    change(
                        'source_note',
                        ($event.target as HTMLTextAreaElement).value,
                    )
                "
            />
        </label>
        <label class="text-sm sm:col-span-2"
            >投稿说明<textarea
                :class="inputClass"
                rows="3"
                :value="modelValue.description"
                maxlength="5000"
                @input="
                    change(
                        'description',
                        ($event.target as HTMLTextAreaElement).value,
                    )
                "
            />
        </label>
        <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2"
            ><input
                type="checkbox"
                :checked="modelValue.show_credit"
                @change="
                    change(
                        'show_credit',
                        ($event.target as HTMLInputElement).checked,
                    )
                "
            />自愿公开投稿署名（默认匿名，不代表图片版权归属）</label
        >
    </div>
</template>
