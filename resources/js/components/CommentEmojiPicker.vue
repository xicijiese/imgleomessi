<script setup lang="ts">
import { stickers } from '@/lib/comment-stickers';
import { Smile, X } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    useId,
    watch,
} from 'vue';
import categories from '../../data/douyin-sticker-categories.json';
const props = defineProps<{ modelValue: string; targetId: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const root = ref<HTMLElement | null>(null),
    open = ref(false),
    query = ref(''),
    selected = ref('all'),
    tabsRoot = ref<HTMLElement | null>(null),
    scrollRoot = ref<HTMLElement | null>(null),
    limit = ref(60),
    notice = ref('');
const id = useId();
const tabs = [{ id: 'all', label: '全部' }, ...categories];
const selectTab = async (value: string) => {
    selected.value = value;
    query.value = '';
    limit.value = 60;
    await nextTick();
    if (scrollRoot.value) scrollRoot.value.scrollTop = 0;
};
const tabKey = async (event: KeyboardEvent) => {
    const current = tabs.findIndex((tab) => tab.id === selected.value);
    const next =
        event.key === 'ArrowRight'
            ? (current + 1) % tabs.length
            : event.key === 'ArrowLeft'
              ? (current + tabs.length - 1) % tabs.length
              : event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? tabs.length - 1
                  : -1;
    if (next < 0) return;
    event.preventDefault();
    await selectTab(tabs[next].id);
    const button = tabsRoot.value?.querySelector<HTMLButtonElement>(
        `[data-category="${selected.value}"]`,
    );
    button?.focus();
    button?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
};
const filtered = computed(() =>
    stickers.filter(
        (item) =>
            (selected.value === 'all' || item.category === selected.value) &&
            item.name.toLowerCase().includes(query.value.trim().toLowerCase()),
    ),
);
watch(query, async () => {
    if (query.value.trim()) {
        selected.value = 'all';
        await nextTick();
        if (tabsRoot.value) tabsRoot.value.scrollLeft = 0;
    }
    if (scrollRoot.value) scrollRoot.value.scrollTop = 0;
    limit.value = 60;
});
const insert = async (value: string) => {
    const field = document.getElementById(
        props.targetId,
    ) as HTMLTextAreaElement | null;
    if (!field) return;
    const start = field.selectionStart ?? props.modelValue.length,
        end = field.selectionEnd ?? start;
    const text =
        props.modelValue.slice(0, start) + value + props.modelValue.slice(end);
    if (text.length > 1000) {
        notice.value = '评论内容不能超过1000个字符。';
        return;
    }
    if ((text.match(/\[dy:[a-f0-9]{16}\]/g)?.length ?? 0) > 20) {
        notice.value = '每条评论最多使用20个图片表情。';
        return;
    }
    emit('update:modelValue', text);
    notice.value = '';
    await nextTick();
    field.setSelectionRange(start + value.length, start + value.length);
};
const closeOutside = (event: PointerEvent) => {
    if (root.value && !root.value.contains(event.target as Node))
        open.value = false;
};
onMounted(() => document.addEventListener('pointerdown', closeOutside));
onUnmounted(() => document.removeEventListener('pointerdown', closeOutside));
</script>
<template>
    <div
        ref="root"
        class="comment-emoji-picker"
        @keydown.esc.stop="open = false"
    >
        <button
            type="button"
            class="comment-emoji-trigger"
            :aria-expanded="open"
            :aria-controls="id"
            @click="open = !open"
        >
            <Smile :size="18" aria-hidden="true" />表情
        </button>
        <section
            v-if="open"
            :id="id"
            class="comment-emoji-panel"
            aria-label="评论表情选择"
        >
            <div
                ref="tabsRoot"
                class="comment-sticker-tabs"
                role="tablist"
                aria-label="表情分类"
                @keydown="tabKey"
            >
                <button
                    v-for="tab in tabs"
                    :id="`${id}-tab-${tab.id}`"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :data-category="tab.id"
                    :aria-selected="selected === tab.id"
                    :tabindex="selected === tab.id ? 0 : -1"
                    :aria-controls="`${id}-results`"
                    @click="selectTab(tab.id)"
                >
                    {{ tab.label }}
                </button>
            </div>
            <div
                :id="`${id}-results`"
                ref="scrollRoot"
                class="comment-emoji-scroll"
                role="tabpanel"
                :aria-labelledby="`${id}-tab-${selected}`"
            >
                <div class="comment-emoji-grid">
                    <button
                        v-for="item in filtered.slice(0, limit)"
                        :key="item.id"
                        type="button"
                        :title="item.name"
                        :aria-label="`插入表情：${item.name}`"
                        @pointerdown.prevent
                        @click="insert(`[dy:${item.id}]`)"
                    >
                        <img
                            :src="item.url"
                            :alt="item.name"
                            width="32"
                            height="32"
                            loading="lazy"
                            decoding="async"
                        />
                    </button>
                </div>
                <p v-if="!filtered.length">没有找到匹配的表情。</p>
                <button
                    v-if="filtered.length > limit"
                    type="button"
                    class="comment-emoji-more"
                    @click="limit += 60"
                >
                    加载更多表情
                </button>
            </div>
            <p v-if="notice" role="status">{{ notice }}</p>
            <div class="comment-emoji-bottom">
                <label class="comment-emoji-search"
                    ><span class="sr-only">搜索表情</span
                    ><input
                        v-model="query"
                        type="search"
                        placeholder="搜索表情"
                /></label>
                <button
                    type="button"
                    class="comment-emoji-close"
                    aria-label="关闭表情面板"
                    @click="open = false"
                >
                    <X :size="20" aria-hidden="true" />
                </button>
            </div>
        </section>
    </div>
</template>
<style scoped>
.comment-sticker-tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    margin-bottom: 10px;
    padding-bottom: 4px;
    scrollbar-width: thin;
    overscroll-behavior-x: contain;
}
.comment-sticker-tabs button {
    flex-shrink: 0;
    min-height: 44px;
    padding: 0 10px;
    white-space: nowrap;
    font-size: 13px;
    border-bottom: 2px solid transparent;
}
.comment-sticker-tabs button[aria-selected='true'] {
    color: #042240;
    border-bottom-color: #ba9f6c;
    background: #98cef026;
}

.comment-emoji-picker {
    position: relative;
    width: fit-content;
    color: #042240;
    font-size: 13px;
}
.comment-emoji-trigger {
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 10px;
}
.comment-emoji-panel {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 0;
    width: 360px;
    max-width: calc(100vw - 40px);
    padding: 12px;
    border: 1px solid #cbd8e2;
    border-radius: 10px;
    background: white;
    box-shadow: 0 10px 32px #04224020;
    z-index: 60;
}
.comment-emoji-bottom {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-top: 10px;
    margin-top: 10px;
    border-top: 1px solid #dce5ec;
}
.comment-emoji-close {
    display: grid;
    place-items: center;
    width: 44px;
    min-height: 44px;
    flex-shrink: 0;
}
.comment-emoji-search {
    flex: 1;
    min-width: 0;
    display: grid;
    gap: 4px;
    margin: 0;
    font-size: 12px;
    color: #526779;
}
.comment-emoji-search input {
    width: 100%;
    padding: 8px;
    border: 1px solid #cbd8e2;
    border-radius: 4px;
    background: white;
}
.comment-emoji-scroll {
    max-height: 240px;
    overflow-y: auto;
    overscroll-behavior: contain;
}
.comment-emoji-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 4px;
}
.comment-emoji-grid button {
    display: grid;
    place-items: center;
    min-height: 44px;
    min-width: 0;
    font-size: 24px;
    border-radius: 4px;
}
.comment-emoji-grid button:hover {
    background: #98cef026;
}
.comment-emoji-grid img {
    width: 32px;
    height: 32px;
    object-fit: contain;
}
.comment-emoji-more {
    width: 100%;
    min-height: 44px;
    color: #094067;
}
button:focus-visible,
input:focus-visible {
    outline: 2px solid #042240;
    outline-offset: 2px;
}
@media (max-width: 767px) {
    .comment-emoji-panel {
        position: fixed;
        inset: auto 12px calc(12px + env(safe-area-inset-bottom));
        width: auto;
        max-width: none;
        max-height: 70dvh;
        overflow: auto;
    }
    .comment-emoji-scroll {
        max-height: 35dvh;
    }
}
</style>
