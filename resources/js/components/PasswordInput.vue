<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });
const attrs = useAttrs();
const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            v-bind="attrs"
            :type="visible ? 'text' : 'password'"
            class="auth-input pr-12!"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-md text-[#526b80] hover:text-[#094067] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#147dc1]"
            :aria-label="visible ? '隐藏密码' : '显示密码'"
            :aria-pressed="visible"
            :aria-controls="typeof attrs.id === 'string' ? attrs.id : undefined"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="size-[18px]" aria-hidden="true" />
            <Eye v-else class="size-[18px]" aria-hidden="true" />
        </button>
    </div>
</template>
