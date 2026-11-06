<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, ImageOff } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface PhotoCard {
    title: string;
    url: string;
    image_url: string | null;
    alt: string;
    event_date: string | null;
    category_summary: string;
    resolution_label: string;
    orientation_label: string;
    watermark_status_label: string;
    tags: Array<{ id: number; name: string }>;
}

const props = defineProps<{ photo: PhotoCard }>();
const imageFailed = ref(false);
watch(
    () => props.photo.image_url,
    () => {
        imageFailed.value = false;
    },
);
</script>

<template>
    <Link
        :href="photo.url"
        class="browser-photo-card"
        :aria-label="`查看图片：${photo.title}`"
    >
        <div class="browser-photo">
            <img
                v-if="photo.image_url && !imageFailed"
                :src="photo.image_url"
                :alt="photo.alt"
                loading="lazy"
                @error="imageFailed = true"
            />
            <div v-else class="browser-image-error">
                <ImageOff aria-hidden="true" /><span>图片暂不可用</span
                ><small>仍可查看档案资料</small>
            </div>
            <span class="browser-entry-arrow"
                ><ArrowUpRight :size="18" aria-hidden="true"
            /></span>
        </div>
        <div class="browser-card-copy">
            <h2>{{ photo.title }}</h2>
            <p class="browser-date">{{ photo.event_date ?? '日期待补充' }}</p>
            <p v-if="photo.category_summary" class="browser-category">
                {{ photo.category_summary }}
            </p>
            <p class="browser-specs">
                <span>{{ photo.resolution_label }}</span
                ><span aria-hidden="true">·</span
                ><span>{{ photo.orientation_label }}</span
                ><span aria-hidden="true">·</span
                ><span>水印{{ photo.watermark_status_label }}</span>
            </p>
            <div v-if="photo.tags.length" class="browser-tags">
                <span v-for="tag in photo.tags.slice(0, 3)" :key="tag.id">{{
                    tag.name
                }}</span>
            </div>
        </div>
    </Link>
</template>
