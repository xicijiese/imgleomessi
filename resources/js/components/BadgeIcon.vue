<script setup lang="ts">
import {
    Heart,
    HeartHandshake,
    Medal,
    MessageCircle,
    Sparkles,
    UserRound,
} from 'lucide-vue-next';

const icons: Record<string, typeof Medal> = {
    'user-round': UserRound,
    heart: Heart,
    'message-circle': MessageCircle,
    'heart-handshake': HeartHandshake,
    sparkles: Sparkles,
};

defineProps<{
    badge: {
        name: string;
        image_url?: string | null;
        color: string;
        icon_key?: string | null;
    };
}>();
</script>

<template>
    <span
        class="inline-flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-sm"
        :style="{
            backgroundColor: badge.image_url ? 'transparent' : badge.color,
        }"
    >
        <img
            v-if="badge.image_url"
            :src="badge.image_url"
            :alt="badge.name"
            class="h-full w-full object-contain"
            loading="lazy"
        />
        <component
            :is="icons[badge.icon_key ?? ''] ?? Medal"
            v-else
            class="h-7 w-7 text-white"
            aria-hidden="true"
        />
    </span>
</template>
