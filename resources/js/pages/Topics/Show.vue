<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ImageOff, Images } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface CategorySummary {
    id: number;
    name: string;
    root_name: string | null;
    root_slug: string | null;
}

interface TagSummary {
    id: number;
    name: string;
    type: string;
}

interface AlbumCard {
    id: number;
    title: string;
    slug: string;
    description: string;
    url: string;
    cover_image_url: string | null;
    cover_alt: string;
    published_at: string | null;
    public_photos_count: number;
    category_summary: string;
    categories: CategorySummary[];
}

interface PhotoCard {
    id: number;
    uuid: string;
    title: string;
    description: string;
    url: string;
    image_url: string | null;
    alt: string;
    event_date: string | null;
    published_at: string | null;
    category_summary: string;
    categories: CategorySummary[];
    tags: TagSummary[];
}

interface TopicDetailPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    topic: {
        title: string;
        slug: string;
        url: string;
        description: string | null;
        cover_image_url: string | null;
        cover_alt: string;
    };
    albums: {
        data: AlbumCard[];
    };
    photos: {
        data: PhotoCard[];
    };
}

const props = defineProps<{
    topicDetail: TopicDetailPayload;
}>();

const coverFailed = ref(false);
const albumImageFailures = ref<Record<number, boolean>>({});
const photoImageFailures = ref<Record<number, boolean>>({});

const hasAlbums = computed(() => props.topicDetail.albums.data.length > 0);
const hasPhotos = computed(() => props.topicDetail.photos.data.length > 0);
const hasAnyContent = computed(() => hasAlbums.value || hasPhotos.value);

const formatDate = (date: string | null) => date ?? '日期待补充';
</script>

<template>
    <SeoHead :seo="topicDetail.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="topicDetail.navigation"
            :site="topicDetail.site"
        />

        <section class="bg-[#d8eefe] px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(360px,0.95fr)] lg:items-center"
            >
                <div class="min-w-0">
                    <Link
                        href="/topics"
                        class="mb-6 inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                        返回专题
                    </Link>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Topic
                    </p>
                    <h1
                        class="max-w-4xl text-4xl leading-tight font-semibold sm:text-5xl"
                    >
                        {{ topicDetail.topic.title }}
                    </h1>
                    <p
                        v-if="topicDetail.topic.description"
                        class="mt-5 max-w-3xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        {{ topicDetail.topic.description }}
                    </p>
                    <div
                        class="mt-5 flex flex-wrap gap-3 text-sm text-[#5f6c7b]"
                    >
                        <span
                            class="inline-flex min-h-11 items-center gap-2 rounded-sm bg-[#fffffe] px-3 shadow-sm"
                        >
                            <Images
                                class="h-4 w-4 text-[#3da9fc]"
                                aria-hidden="true"
                            />
                            {{ topicDetail.albums.data.length }} 个关联相册
                        </span>
                        <span
                            class="inline-flex min-h-11 items-center gap-2 rounded-sm bg-[#fffffe] px-3 shadow-sm"
                        >
                            <Images
                                class="h-4 w-4 text-[#3da9fc]"
                                aria-hidden="true"
                            />
                            {{ topicDetail.photos.data.length }} 张精选图片
                        </span>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] shadow-sm"
                >
                    <div class="aspect-[16/10] bg-[#d8eefe]">
                        <img
                            v-if="
                                topicDetail.topic.cover_image_url &&
                                !coverFailed
                            "
                            :src="topicDetail.topic.cover_image_url"
                            :alt="topicDetail.topic.cover_alt"
                            class="h-full w-full object-cover"
                            @error="coverFailed = true"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                        >
                            <ImageOff class="h-10 w-10" aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div v-if="hasAnyContent" class="grid gap-12">
                    <section
                        v-if="hasAlbums"
                        aria-labelledby="topic-albums-heading"
                    >
                        <div
                            class="mb-6 flex flex-wrap items-end justify-between gap-4"
                        >
                            <div>
                                <h2
                                    id="topic-albums-heading"
                                    class="text-2xl font-semibold text-[#094067]"
                                >
                                    关联相册
                                </h2>
                                <p class="mt-2 text-sm text-[#5f6c7b]">
                                    按后台配置顺序展示公开相册。
                                </p>
                            </div>
                            <Link
                                href="/albums"
                                class="inline-flex h-11 items-center rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                            >
                                查看全部相册
                            </Link>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <Link
                                v-for="album in topicDetail.albums.data"
                                :key="album.id"
                                :href="album.url"
                                class="group overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                                :aria-label="`查看相册：${album.title}`"
                            >
                                <div
                                    class="aspect-[4/3] overflow-hidden bg-[#d8eefe]"
                                >
                                    <img
                                        v-if="
                                            album.cover_image_url &&
                                            !albumImageFailures[album.id]
                                        "
                                        :src="album.cover_image_url"
                                        :alt="album.cover_alt"
                                        loading="lazy"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                        @error="
                                            albumImageFailures[album.id] = true
                                        "
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                                    >
                                        <ImageOff
                                            class="h-8 w-8"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </div>
                                <div class="grid min-h-36 gap-3 p-4">
                                    <div>
                                        <h3
                                            class="line-clamp-2 text-base leading-6 font-semibold text-[#094067]"
                                        >
                                            {{ album.title }}
                                        </h3>
                                        <p class="mt-2 text-sm text-[#5f6c7b]">
                                            {{ formatDate(album.published_at) }}
                                        </p>
                                    </div>
                                    <p
                                        v-if="album.description"
                                        class="line-clamp-2 text-sm leading-6 text-[#5f6c7b]"
                                    >
                                        {{ album.description }}
                                    </p>
                                    <div
                                        class="flex flex-wrap gap-2 text-xs font-medium text-[#094067]"
                                    >
                                        <span
                                            class="rounded-full bg-[#d8eefe] px-2.5 py-1"
                                            >{{
                                                album.public_photos_count
                                            }}
                                            张图片</span
                                        >
                                        <span
                                            v-if="album.category_summary"
                                            class="rounded-full bg-[#d8eefe] px-2.5 py-1"
                                            >{{ album.category_summary }}</span
                                        >
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section
                        v-if="hasPhotos"
                        aria-labelledby="topic-photos-heading"
                    >
                        <div class="mb-6">
                            <h2
                                id="topic-photos-heading"
                                class="text-2xl font-semibold text-[#094067]"
                            >
                                精选图片
                            </h2>
                            <p class="mt-2 text-sm text-[#5f6c7b]">
                                按后台配置顺序展示公开图片。
                            </p>
                        </div>

                        <div
                            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                        >
                            <Link
                                v-for="photo in topicDetail.photos.data"
                                :key="photo.uuid"
                                :href="photo.url"
                                class="group overflow-hidden rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                                :aria-label="`查看图片：${photo.title}`"
                            >
                                <div
                                    class="aspect-[4/3] overflow-hidden bg-[#d8eefe]"
                                >
                                    <img
                                        v-if="
                                            photo.image_url &&
                                            !photoImageFailures[photo.id]
                                        "
                                        :src="photo.image_url"
                                        :alt="photo.alt"
                                        loading="lazy"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                        @error="
                                            photoImageFailures[photo.id] = true
                                        "
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                                    >
                                        <ImageOff
                                            class="h-8 w-8"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </div>
                                <div class="grid min-h-36 gap-3 p-4">
                                    <div>
                                        <h3
                                            class="line-clamp-2 text-base leading-6 font-semibold text-[#094067]"
                                        >
                                            {{ photo.title }}
                                        </h3>
                                        <p class="mt-2 text-sm text-[#5f6c7b]">
                                            {{ formatDate(photo.event_date) }}
                                        </p>
                                    </div>
                                    <p
                                        v-if="photo.category_summary"
                                        class="truncate text-sm text-[#5f6c7b]"
                                    >
                                        {{ photo.category_summary }}
                                    </p>
                                    <div
                                        v-if="photo.tags.length"
                                        class="flex flex-wrap gap-2"
                                    >
                                        <span
                                            v-for="tag in photo.tags.slice(
                                                0,
                                                3,
                                            )"
                                            :key="tag.id"
                                            class="rounded-full bg-[#d8eefe] px-2.5 py-1 text-xs font-medium text-[#094067]"
                                        >
                                            {{ tag.name }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>
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
                        这个专题还没有可展示内容
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[#5f6c7b]"
                    >
                        后台为该专题配置公开相册或精选图片后，这里会展示专题内容。
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
