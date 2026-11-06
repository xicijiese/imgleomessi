<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps<{
    meta: { current_page: number; last_page: number };
    links: { prev: string | null; next: string | null };
    label: string;
}>();
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="browser-pagination"
        :aria-label="label"
    >
        <Link v-if="links.prev" :href="links.prev" class="browser-page-button"
            ><ChevronLeft :size="16" aria-hidden="true" />上一页</Link
        >
        <button v-else type="button" class="browser-page-button" disabled>
            <ChevronLeft :size="16" aria-hidden="true" />上一页
        </button>
        <p>
            第 <strong>{{ meta.current_page }}</strong> /
            {{ meta.last_page }} 页
        </p>
        <Link
            v-if="links.next"
            :href="links.next"
            class="browser-page-button browser-page-next"
            >下一页<ChevronRight :size="16" aria-hidden="true"
        /></Link>
        <button v-else type="button" class="browser-page-button" disabled>
            下一页<ChevronRight :size="16" aria-hidden="true" />
        </button>
    </nav>
</template>
