<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SimilarPhotoGrid from '@/components/SimilarPhotoGrid.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    site: { name: string; logo_url: string | null; search_placeholder: string };
    navigation: { label: string; url: string }[];
    imageSearch: {
        id: string;
        status: string;
        error_message: string | null;
    } | null;
    photos: {
        id: number;
        title: string;
        url: string;
        image_url: string | null;
    }[];
}>();
const form = useForm<{ image: File | null }>({ image: null });
const preview = ref<string | null>(null);
const pollingStopped = ref(false);
const waiting = computed(() =>
    ['pending', 'running'].includes(props.imageSearch?.status ?? ''),
);
let timer: ReturnType<typeof setTimeout> | undefined;
let pollCount = 0;
let disposed = false;
const poll = () => {
    if (disposed) return;
    if (timer) clearTimeout(timer);
    if (waiting.value && pollCount < 60) {
        timer = setTimeout(() => {
            pollCount++;
            router.reload({ only: ['imageSearch', 'photos'], onFinish: poll });
        }, 3000);
    } else if (waiting.value) {
        pollingStopped.value = true;
    }
};
onMounted(poll);
watch(
    () => props.imageSearch?.id,
    () => {
        pollCount = 0;
        pollingStopped.value = false;
        poll();
    },
);
onBeforeUnmount(() => {
    disposed = true;
    if (timer) clearTimeout(timer);
    if (preview.value) URL.revokeObjectURL(preview.value);
});
const choose = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
    form.clearErrors();
    form.image = file;
    if (file && file.size > 5 * 1024 * 1024) {
        form.setError('image', '图片大小不能超过 5 MB。');
        form.image = null;
    } else if (file) {
        preview.value = URL.createObjectURL(file);
    }
};
const submit = () =>
    form.post('/search/image', { forceFormData: true, preserveScroll: true });
</script>

<template>
    <Head title="以图搜图"
        ><meta name="robots" content="noindex,nofollow"
    /></Head>
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="navigation" :site="site" />
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <Link href="/search" class="text-sm underline underline-offset-4"
                >返回文字搜索</Link
            >
            <h1 class="mt-6 text-3xl font-semibold">以图搜图</h1>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-[#5f6c7b]">
                选择一张图片，查找图库中画面接近的公开图片。支持同图及相近版本，裁剪或大幅修改的图片可能无法匹配。查询图片仅临时用于检索，不会发布到图库。
            </p>
            <form
                class="my-8 flex flex-col items-start gap-4 rounded-xl border border-[#90b4ce]/40 p-5 sm:flex-row sm:items-center"
                @submit.prevent="submit"
            >
                <img
                    v-if="preview"
                    :src="preview"
                    alt="本次查询图片预览"
                    class="h-24 w-32 rounded-lg object-contain"
                />
                <div class="min-w-0 flex-1">
                    <label
                        for="query-image"
                        class="mb-2 block text-sm font-semibold"
                        >选择查询图片</label
                    >
                    <input
                        id="query-image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        :disabled="form.processing"
                        aria-describedby="image-help image-error"
                        class="max-w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#d8eefe] file:px-3 file:py-2 file:text-[#094067] focus-visible:outline-2 focus-visible:outline-offset-4"
                        @change="choose"
                    />
                    <p id="image-help" class="mt-2 text-xs text-[#5f6c7b]">
                        JPG、PNG、WebP；不超过 5 MB、2000 万像素。结果保留 1
                        小时。
                    </p>
                    <p
                        id="image-error"
                        role="alert"
                        class="mt-2 text-sm text-red-700"
                    >
                        {{ form.errors.image }}
                    </p>
                </div>
                <button
                    type="submit"
                    :disabled="!form.image || form.processing"
                    class="rounded-lg bg-[#094067] px-5 py-3 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#094067] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? '正在提交…' : '查找相似图片' }}
                </button>
            </form>
            <div
                v-if="waiting"
                role="status"
                class="rounded-xl bg-[#d8eefe] p-6"
            >
                <p>
                    {{
                        pollingStopped
                            ? '查询仍在排队，可以稍后刷新查看。'
                            : '正在查找相似图片，结果会自动更新。'
                    }}
                </p>
                <button
                    v-if="pollingStopped"
                    type="button"
                    class="mt-3 underline"
                    @click="router.reload({ only: ['imageSearch', 'photos'] })"
                >
                    刷新查询结果
                </button>
            </div>
            <p
                v-else-if="imageSearch?.status === 'failed'"
                role="alert"
                class="text-red-700"
            >
                {{ imageSearch.error_message }}
            </p>
            <section
                v-else-if="imageSearch?.status === 'done'"
                aria-labelledby="image-results"
            >
                <h2 id="image-results" class="mb-5 text-xl font-semibold">
                    找到 {{ photos.length }} 张相似图片
                </h2>
                <SimilarPhotoGrid v-if="photos.length" :photos="photos" />
                <p v-else class="text-sm text-[#5f6c7b]">
                    暂未找到匹配图片，可以更换图片或使用文字搜索。图库中的图片需要先完成相似特征分析。
                </p>
            </section>
        </div>
    </main>
</template>
