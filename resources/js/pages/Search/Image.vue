<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ArrowLeft, Clock3, ImageOff, Images } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import '../../../css/archive-browser.css';
import './Image.css';

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

const root = ref<HTMLElement | null>(null);
const imageFailures = ref<Record<number, boolean>>({});
let motion: gsap.MatchMedia | undefined;
onMounted(() => {
    motion = gsap.matchMedia();
    motion.add(
        '(prefers-reduced-motion: no-preference)',
        () => {
            gsap.from('.image-search-heading, .image-query-panel', {
                y: 6,
                duration: 0.24,
                stagger: 0.04,
                clearProps: 'transform',
            });
        },
        root.value ?? undefined,
    );
});
onBeforeUnmount(() => motion?.revert());
watch(
    () => props.imageSearch?.id,
    () => {
        imageFailures.value = {};
    },
);
</script>
<template>
    <Head title="以图搜图"
        ><meta name="robots" content="noindex,nofollow"
    /></Head>
    <main ref="root" class="archive-browser archive-image-search">
        <PublicHeader :navigation="navigation" :site="site" theme="afa" />
        <div class="browser-container">
            <Link href="/search" class="image-search-back"
                ><ArrowLeft :size="18" aria-hidden="true" />返回文字搜索</Link
            >
            <div class="image-search-heading">
                <h1>以图搜图</h1>
                <p>选择一张图片，查找图库中画面接近的公开图片。</p>
            </div>
            <form :aria-busy="form.processing" @submit.prevent="submit">
                <div
                    class="image-query-panel"
                    :class="{ 'has-preview': preview }"
                >
                    <div class="image-query-preview">
                        <img
                            v-if="preview"
                            :src="preview"
                            alt="本次查询图片预览"
                        />
                        <div v-else class="image-preview-placeholder">
                            <Images :size="32" aria-hidden="true" /><span
                                >查询图片预览</span
                            >
                        </div>
                    </div>
                    <div class="image-query-controls">
                        <label for="query-image">选择查询图片</label
                        ><input
                            id="query-image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            :disabled="form.processing"
                            :aria-invalid="Boolean(form.errors.image)"
                            aria-describedby="image-help privacy-help image-error"
                            @change="choose"
                        /><span class="image-query-filename">{{
                            form.image?.name ?? '未选择图片'
                        }}</span>
                        <p id="image-help">
                            JPG、PNG、WebP · 不超过 5 MB、2000 万像素
                        </p>
                        <p id="privacy-help">
                            查询图片仅临时用于检索，不会发布到图库；结果保留 1
                            小时。
                        </p>
                        <p id="image-error" role="alert">
                            {{ form.errors.image }}
                        </p>
                    </div>
                    <button
                        class="browser-primary"
                        type="submit"
                        :disabled="!form.image || form.processing"
                    >
                        {{ form.processing ? '正在提交…' : '查找相似图片' }}
                    </button>
                </div>
            </form>
            <p class="image-search-limitation">
                支持同图及相近版本，裁剪或大幅修改的图片可能无法匹配。
            </p>
            <section
                v-if="waiting"
                class="image-search-status"
                role="status"
                aria-live="polite"
            >
                <Clock3 :size="26" aria-hidden="true" />
                <div>
                    <h2>
                        {{
                            pollingStopped
                                ? '查询仍未完成'
                                : imageSearch?.status === 'pending'
                                  ? '查询正在排队'
                                  : '正在查找相似图片'
                        }}
                    </h2>
                    <p>
                        {{
                            pollingStopped
                                ? '自动更新已暂停，可以稍后手动刷新查看。'
                                : '结果会自动更新，请稍候。'
                        }}
                    </p>
                    <button
                        v-if="pollingStopped"
                        type="button"
                        class="browser-outline"
                        @click="
                            router.reload({ only: ['imageSearch', 'photos'] })
                        "
                    >
                        刷新查询结果
                    </button>
                </div>
            </section>
            <section
                v-else-if="imageSearch?.status === 'failed'"
                role="alert"
                class="image-search-error"
            >
                <h2>这次查询没有完成</h2>
                <p>{{ imageSearch.error_message }}</p>
            </section>
            <section
                v-else-if="imageSearch?.status === 'done'"
                class="image-search-results"
                aria-labelledby="image-results"
            >
                <div class="image-results-heading">
                    <h2 id="image-results">
                        找到 {{ photos.length }} 张相似图片
                    </h2>
                    <span>公开图片</span>
                </div>
                <div v-if="photos.length" class="browser-photo-grid">
                    <Link
                        v-for="photo in photos"
                        :key="photo.id"
                        :href="photo.url"
                        class="browser-photo-card"
                        :aria-label="`查看图片：${photo.title}`"
                        ><div class="browser-photo">
                            <img
                                v-if="
                                    photo.image_url && !imageFailures[photo.id]
                                "
                                :src="photo.image_url"
                                :alt="photo.title"
                                loading="lazy"
                                @error="imageFailures[photo.id] = true"
                            />
                            <div
                                v-else
                                class="browser-image-error"
                                role="img"
                                :aria-label="photo.title + '，预览暂不可用'"
                            >
                                <ImageOff :size="28" aria-hidden="true" />
                            </div>
                            <span class="browser-entry-arrow" aria-hidden="true"
                                >↗</span
                            >
                        </div>
                        <div class="browser-card-copy">
                            <h3>{{ photo.title }}</h3>
                        </div></Link
                    >
                </div>
                <div v-else class="browser-empty-state">
                    <h3>暂未找到匹配图片</h3>
                    <p>可以更换图片或使用文字搜索，试试清晰、完整的画面。</p>
                    <div>
                        <Link href="/search" class="browser-outline"
                            >返回文字搜索 →</Link
                        >
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
