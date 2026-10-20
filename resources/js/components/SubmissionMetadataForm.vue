<script setup lang="ts">
import type {
    SubmissionDictionaries,
    SubmissionMetadata,
} from '@/types/submissions';
import { computed, ref } from 'vue';
const props = defineProps<{
    modelValue: SubmissionMetadata;
    dictionaries: SubmissionDictionaries;
    idPrefix: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [SubmissionMetadata] }>();
const tagsQuery = ref('');
const filteredTags = computed(() =>
    props.dictionaries.tags.filter(
        (tag) =>
            tag.name.includes(tagsQuery.value.trim()) ||
            props.modelValue.tag_ids.includes(tag.id),
    ),
);
function change<K extends keyof SubmissionMetadata>(
    key: K,
    value: SubmissionMetadata[K],
) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
function categoryValue(ids: number[]) {
    return props.modelValue.category_ids.find((id) => ids.includes(id)) ?? '';
}
function changeCategory(ids: number[], event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    change('category_ids', [
        ...props.modelValue.category_ids.filter((id) => !ids.includes(id)),
        ...(value ? [Number(value)] : []),
    ]);
}
const inputClass =
    'mt-1 min-h-11 w-full rounded-sm border border-[#90b4ce] bg-white px-3 py-2 text-sm text-[#094067] focus:outline-2 focus:outline-[#3da9fc]';
</script>
<template>
    <div class="grid gap-4 sm:grid-cols-2">
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
        <label
            v-for="category in dictionaries.categories"
            :key="category.id"
            class="text-sm"
            >{{ category.name
            }}<select
                :class="inputClass"
                :value="
                    categoryValue(category.children.map((child) => child.id))
                "
                @change="
                    changeCategory(
                        category.children.map((child) => child.id),
                        $event,
                    )
                "
            >
                <option value="">不确定 / 暂不填写</option>
                <option
                    v-for="child in category.children"
                    :key="child.id"
                    :value="child.id"
                >
                    {{ child.name }}
                </option>
            </select></label
        >
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
        <div class="text-sm">
            <label :for="`${idPrefix}-tag-search`">搜索标签</label
            ><input
                :id="`${idPrefix}-tag-search`"
                v-model="tagsQuery"
                type="search"
                :class="inputClass"
                placeholder="输入标签名称"
            /><label :for="`${idPrefix}-tags`" class="mt-2 block"
                >标签（可多选）</label
            ><select
                :id="`${idPrefix}-tags`"
                :class="inputClass"
                multiple
                :value="modelValue.tag_ids.map(String)"
                @change="
                    change(
                        'tag_ids',
                        Array.from(
                            ($event.target as HTMLSelectElement)
                                .selectedOptions,
                            (item) => Number(item.value),
                        ),
                    )
                "
            >
                <option
                    v-for="tag in filteredTags"
                    :key="tag.id"
                    :value="tag.id"
                >
                    {{ tag.name }}
                </option>
            </select>
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
            >来源说明（必填，不清楚可填“不详”）<textarea
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
