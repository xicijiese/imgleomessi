<script setup lang="ts">
import CommentThreadRow from '@/components/CommentThreadRow.vue';
import { shareRequest } from '@/lib/sharing';
import type { ThreadComment } from '@/types/comments';
import { ref, watch } from 'vue';
const props = defineProps<{
    initial: ThreadComment[];
    uuid: string;
    canInteract: boolean;
    hasMore: boolean;
    blockedReason?: string | null;
}>();
const emit = defineEmits<{ report: [id: number] }>();
const items = ref(props.initial),
    page = ref(1),
    more = ref(props.hasMore),
    busy = ref(false),
    error = ref('');
watch(
    () => props.initial,
    (value) => {
        items.value = value;
        page.value = 1;
        more.value = props.hasMore;
    },
);
const load = async () => {
    if (busy.value) return;
    busy.value = true;
    try {
        const r = await shareRequest<{
            data: ThreadComment[];
            has_more: boolean;
        }>(`/photos/${props.uuid}/discussion?comment_page=${page.value + 1}`);
        items.value.push(...r.data);
        page.value++;
        more.value = r.has_more;
        error.value = '';
    } catch {
        error.value = '评论加载失败，请重试。';
    } finally {
        busy.value = false;
    }
};
</script>
<template>
    <div class="comment-thread-list">
        <CommentThreadRow
            v-for="comment in items"
            :key="comment.id"
            :comment="comment"
            :uuid="uuid"
            :can-interact="canInteract"
            :blocked-reason="blockedReason"
            @report="emit('report', $event)"
        /><button
            v-if="more"
            type="button"
            class="comment-load-more"
            :disabled="busy"
            @click="load"
        >
            {{ busy ? '正在加载' : '加载更多评论' }}
        </button>
        <p v-if="error" role="alert">{{ error }}</p>
    </div>
</template>
