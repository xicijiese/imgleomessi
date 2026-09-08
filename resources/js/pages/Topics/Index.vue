<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ImageOff, Images } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface TopicCard {
    title: string;
    url: string;
    cover_image_url: string | null;
}

interface TopicIndexPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    topics: {
        data: TopicCard[];
    };
}

const props = defineProps<{
    topicIndex: TopicIndexPayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});

const hasTopics = computed(() => props.topicIndex.topics.data.length > 0);
</script>

<template>
    <SeoHead :seo="topicIndex.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="topicIndex.navigation"
            :site="topicIndex.site"
        />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Topics
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">专题</h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        按世界杯、金球奖、生涯阶段和经典组合整理梅西影像线索。
                    </p>
                </div>

                <div
                    class="rounded-sm bg-[#fffffe] px-4 py-3 text-sm text-[#5f6c7b] shadow-sm"
                >
                    <span class="font-semibold text-[#094067]">{{
                        topicIndex.topics.data.length
                    }}</span>
                    个公开专题
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div
                    v-if="hasTopics"
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="topic in topicIndex.topics.data"
                        :key="topic.url"
                        :href="topic.url"
                        class="group relative block overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#d8eefe] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                        :aria-label="`查看专题：${topic.title}`"
                    >
                        <div
                            class="aspect-[16/10] overflow-hidden bg-[#d8eefe]"
                        >
                            <img
                                v-if="
                                    topic.cover_image_url &&
                                    !imageFailures[topic.url]
                                "
                                :src="topic.cover_image_url"
                                :alt="topic.title"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                @error="imageFailures[topic.url] = true"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                            >
                                <ImageOff class="h-9 w-9" aria-hidden="true" />
                            </div>
                        </div>
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-[#094067]/80 via-[#094067]/20 to-transparent transition group-hover:from-[#094067]/90"
                        />
                        <div class="absolute inset-x-0 bottom-0 p-5">
                            <h2
                                class="text-2xl leading-tight font-semibold text-[#fffffe] sm:text-3xl"
                            >
                                {{ topic.title }}
                            </h2>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="rounded-sm border border-dashed border-[#90b4ce]/60 bg-[#d8eefe]/55 px-6 py-16 text-center"
                >
                    <Images
                        class="mx-auto h-12 w-12 text-[#3da9fc]"
                        aria-hidden="true"
                    />
                    <h2 class="mt-5 text-2xl font-semibold text-[#094067]">
                        还没有可展示的公开专题
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]"
                    >
                        后台在首页配置中添加并启用专题后，这里会展示专题入口。
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <Link
                            href="/albums"
                            class="inline-flex h-11 items-center rounded-sm bg-[#3da9fc] px-4 text-sm font-semibold text-[#fffffe] hover:bg-[#2f8ed8]"
                        >
                            查看相册
                        </Link>
                        <Link
                            href="/photos"
                            class="inline-flex h-11 items-center rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                        >
                            浏览图库
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
