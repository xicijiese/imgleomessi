<script setup lang="ts">
import { commentParts } from '@/lib/comment-stickers';
import { computed } from 'vue';
const props = defineProps<{ text: string }>();
const parts = computed(() => commentParts(props.text));
</script>
<template>
    <span class="comment-rich-text"
        ><template v-for="(part, index) in parts" :key="index"
            ><img
                v-if="part.url"
                :src="part.url"
                :alt="`[${part.name}]`"
                :title="part.name"
                width="28"
                height="28"
                loading="lazy"
                decoding="async"
                class="comment-inline-sticker"
            /><template v-else>{{ part.text }}</template></template
        ></span
    >
</template>
<style scoped>
.comment-rich-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.comment-inline-sticker {
    display: inline-block;
    width: 28px;
    height: 28px;
    object-fit: contain;
    vertical-align: middle;
    margin: 0 2px;
}
</style>
