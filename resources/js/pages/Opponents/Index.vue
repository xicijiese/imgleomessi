<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, ImageOff, RotateCcw, Search } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface OpponentCard {
    id: number;
    name: string;
    slug: string;
    country: string | null;
    description: string;
    url: string;
    gallery_url: string;
    cover_image_url: string | null;
    cover_alt: string;
    public_photos_count: number;
    public_albums_count: number;
    latest_event_date: string | null;
}

interface ArchiveIndexPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    dimension: {
        type: 'opponents';
        label: string;
        eyebrow: string;
        title: string;
        subtitle: string;
        path: string;
        empty_title: string;
        empty_description: string;
    };
    summary: {
        opponents_count: number;
        photos_count: number;
        albums_count: number;
    };
    filters: {
        q: string;
    };
    opponents: OpponentCard[];
}

const props = defineProps<{
    archive: ArchiveIndexPayload;
}>();

const imageFailures = ref<Record<number, boolean>>({});
const form = reactive({
    q: props.archive.filters.q,
});
const hasOpponents = computed(() => props.archive.opponents.length > 0);
const formatDate = (date: string | null) => date ?? '日期待补充';

const applyFilters = () => {
    router.get(
        props.archive.dimension.path,
        { q: form.q.trim() || undefined },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    form.q = '';
    router.get(props.archive.dimension.path, {}, { preserveScroll: true, replace: true });
};
</script>

<template>
    <SeoHead :seo="archive.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="archive.navigation" :site="archive.site" />

        <section class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        {{ archive.dimension.eyebrow }}
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">
                        {{ archive.dimension.title }}
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base">
                        {{ archive.dimension.subtitle }}
                    </p>
                </div>

                <div class="grid gap-2 rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm sm:grid-cols-3 sm:gap-5">
                    <span><strong class="text-[#094067]">{{ archive.summary.opponents_count }}</strong> 个对手</span>
                    <span><strong class="text-[#094067]">{{ archive.summary.photos_count }}</strong> 张图片</span>
                    <span><strong class="text-[#094067]">{{ archive.summary.albums_count }}</strong> 个相册</span>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <form class="mb-8 grid gap-3 rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto_auto]" @submit.prevent="applyFilters">
                    <label class="block">
                        <span class="sr-only">关键词</span>
                        <span class="flex h-11 items-center rounded-sm border border-[#90b4ce]/60 px-3">
                            <Search class="mr-2 h-4 w-4 shrink-0 text-[#5f6c7b]" aria-hidden="true" />
                            <input
                                v-model="form.q"
                                type="search"
                                class="w-full bg-transparent text-sm outline-none placeholder:text-[#5f6c7b]"
                                placeholder="搜索对手名称、国家或别名"
                            />
                        </span>
                    </label>
                    <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-sm bg-[#3da9fc] px-4 text-sm font-semibold text-[#fffffe] hover:bg-[#094067]">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        筛选
                    </button>
                    <button type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]" @click="resetFilters">
                        <RotateCcw class="h-4 w-4" aria-hidden="true" />
                        重置
                    </button>
                </form>

                <div v-if="hasOpponents" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="item in archive.opponents" :key="item.id" class="overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm">
                        <Link :href="item.url" class="group block aspect-[4/3] overflow-hidden bg-[#d8eefe]" :aria-label="`浏览${item.name}`">
                            <img
                                v-if="item.cover_image_url && !imageFailures[item.id]"
                                :src="item.cover_image_url"
                                :alt="item.cover_alt"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                @error="imageFailures[item.id] = true"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]">
                                <ImageOff class="h-8 w-8" aria-hidden="true" />
                            </div>
                        </Link>
                        <div class="grid gap-4 p-5">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-semibold text-[#094067]">
                                        {{ item.name }}
                                    </h2>
                                    <span v-if="item.country" class="rounded-full bg-[#d8eefe] px-2.5 py-1 text-xs font-semibold text-[#094067]">
                                        {{ item.country }}
                                    </span>
                                </div>
                                <p v-if="item.description" class="mt-2 line-clamp-2 text-sm leading-6 text-[#5f6c7b]">
                                    {{ item.description }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold text-[#094067]">
                                <span class="rounded-full bg-[#d8eefe] px-3 py-1.5">{{ item.public_photos_count }} 张图片</span>
                                <span class="rounded-full bg-[#d8eefe] px-3 py-1.5">{{ item.public_albums_count }} 个相册</span>
                                <span class="rounded-full bg-[#d8eefe] px-3 py-1.5">{{ formatDate(item.latest_event_date) }}</span>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <Link :href="item.url" class="inline-flex h-10 items-center gap-1 rounded-sm bg-[#3da9fc] px-3 text-sm font-semibold text-[#fffffe] hover:bg-[#094067]">
                                    进入
                                    <ChevronRight class="h-4 w-4" aria-hidden="true" />
                                </Link>
                                <Link :href="item.gallery_url" class="inline-flex h-10 items-center rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]">
                                    返回图库
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-sm bg-[#d8eefe] px-6 py-14 text-center">
                    <h2 class="text-2xl font-semibold text-[#094067]">
                        {{ archive.dimension.empty_title }}
                    </h2>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]">
                        {{ archive.dimension.empty_description }}
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>