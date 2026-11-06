<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { ref, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{ label?: string }>(), { label: '密码' });
const attrs = useAttrs();
const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            v-bind="attrs"
            :type="visible ? 'text' : 'password'"
            class="auth-input pr-16!"
        />
        <button
            type="button"
            class="absolute inset-y-0.5 right-0.5 flex min-w-14 items-center justify-center rounded-sm px-2.5 text-[13px] text-[#526779] hover:bg-[#edf7fd] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#042240]"
            :aria-label="`${visible ? '隐藏' : '显示'}${label}`"
            :aria-pressed="visible"
            :aria-controls="typeof attrs.id === 'string' ? attrs.id : undefined"
            @click="visible = !visible"
        >
            {{ visible ? '隐藏' : '显示' }}
        </button>
    </div>
</template>
