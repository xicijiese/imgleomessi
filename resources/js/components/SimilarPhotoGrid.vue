<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImageOff } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    photos: {
        id: number;
        title: string;
        url: string;
        image_url: string | null;
    }[];
}>();
const failures = ref<Record<number, boolean>>({});
</script>

<template>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <Link
            v-for="photo in photos"
            :key="photo.id"
            :href="photo.url"
            class="group overflow-hidden rounded-xl border border-[#90b4ce]/30 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#094067]"
        >
            <div class="aspect-[4/3] overflow-hidden bg-[#d8eefe]">
                <img
                    v-if="photo.image_url && !failures[photo.id]"
                    :src="photo.image_url"
                    :alt="photo.title"
                    loading="lazy"
                    class="h-full w-full object-cover"
                    @error="failures[photo.id] = true"
                />
                <div
                    v-else
                    class="flex h-full items-center justify-center text-[#094067]"
                    role="img"
                    :aria-label="photo.title + '，预览暂不可用'"
                >
                    <ImageOff class="h-8 w-8" aria-hidden="true" />
                </div>
            </div>
            <p
                class="truncate p-3 text-sm font-medium text-[#094067] group-hover:underline"
            >
                {{ photo.title }}
            </p>
        </Link>
    </div>
</template>
