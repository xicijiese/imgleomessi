<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import SharePanel from '@/components/SharePanel.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ArrowLeft, ChevronDown, ImageOff, Images } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import '../../../css/archive-browser.css';
import '../Albums/Albums.css';
import './Topics.css';

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

// 复用相册的紧凑布局；专题只折叠介绍，原内容及顺序保持。
const root = ref<HTMLElement | null>(null);
const isMobile = ref(false);
const infoExpanded = ref(false);
const infoVisible = computed(() => !isMobile.value || infoExpanded.value);
let mobileQuery: MediaQueryList | undefined;
let motion: gsap.MatchMedia | undefined;
const syncMobile = () => {
    isMobile.value = mobileQuery?.matches ?? false;
};
onMounted(() => {
    mobileQuery = window.matchMedia('(max-width: 767px)');
    syncMobile();
    mobileQuery.addEventListener('change', syncMobile);
    motion = gsap.matchMedia();
    motion.add(
        '(prefers-reduced-motion: no-preference)',
        () => {
            gsap.from('.album-intro', {
                y: 6,
                duration: 0.24,
                clearProps: 'transform',
            });
        },
        root.value ?? undefined,
    );
});
onUnmounted(() => {
    mobileQuery?.removeEventListener('change', syncMobile);
    motion?.revert();
});
watch(
    () => props.topicDetail.topic.slug,
    () => {
        infoExpanded.value = false;
        coverFailed.value = false;
        albumImageFailures.value = {};
        photoImageFailures.value = {};
    },
);
</script>
<template>
    <SeoHead :seo="topicDetail.seo" />
    <main ref="root" class="archive-browser archive-albums archive-topics">
        <PublicHeader
            :navigation="topicDetail.navigation"
            :site="topicDetail.site"
            theme="afa"
        />
        <div class="browser-container">
            <Link href="/topics" class="album-back"
                ><ArrowLeft :size="18" aria-hidden="true" />返回专题</Link
            >
            <div class="album-intro">
                <div class="album-cover topic-intro-cover">
                    <img
                        v-if="topicDetail.topic.cover_image_url && !coverFailed"
                        :src="topicDetail.topic.cover_image_url"
                        :alt="topicDetail.topic.cover_alt"
                        @error="coverFailed = true"
                    />
                    <div
                        v-else
                        class="browser-image-error"
                        role="img"
                        :aria-label="
                            topicDetail.topic.cover_alt + '，封面暂不可用'
                        "
                    >
                        <ImageOff :size="28" aria-hidden="true" /><span
                            >封面暂时无法显示</span
                        >
                    </div>
                </div>
                <div class="album-intro-copy">
                    <div class="album-title-row">
                        <h1>{{ topicDetail.topic.title }}</h1>
                        <SharePanel
                            type="topic"
                            :target-key="topicDetail.topic.slug"
                            :title="topicDetail.topic.title"
                            theme="archive"
                        />
                    </div>
                    <p class="album-summary">
                        {{ topicDetail.albums.data.length }} 个关联相册 ·
                        {{ topicDetail.photos.data.length }} 张精选图片
                    </p>
                    <button
                        v-if="topicDetail.topic.description"
                        type="button"
                        class="album-info-toggle"
                        :aria-expanded="infoVisible"
                        aria-controls="topic-info"
                        @click="infoExpanded = !infoExpanded"
                    >
                        <strong>专题介绍</strong
                        ><span>{{ infoVisible ? '收起' : '展开' }}</span
                        ><ChevronDown :size="18" aria-hidden="true" />
                    </button>
                    <div v-show="infoVisible" id="topic-info">
                        <p
                            v-if="topicDetail.topic.description"
                            class="album-description"
                        >
                            {{ topicDetail.topic.description }}
                        </p>
                    </div>
                </div>
            </div>
            <template v-if="hasAnyContent">
                <section
                    v-if="hasAlbums"
                    class="topic-section"
                    aria-labelledby="topic-albums-heading"
                >
                    <div class="topic-section-heading">
                        <div>
                            <h2 id="topic-albums-heading">关联相册</h2>
                            <p>按后台配置顺序展示公开相册。</p>
                        </div>
                        <Link href="/albums"
                            >查看全部相册
                            <span aria-hidden="true">→</span></Link
                        >
                    </div>
                    <div
                        class="album-grid"
                        :class="{
                            'topic-single-album':
                                topicDetail.albums.data.length === 1,
                        }"
                    >
                        <Link
                            v-for="album in topicDetail.albums.data"
                            :key="album.id"
                            :href="album.url"
                            class="album-card"
                            :aria-label="`查看相册：${album.title}`"
                            ><div class="album-cover">
                                <img
                                    v-if="
                                        album.cover_image_url &&
                                        !albumImageFailures[album.id]
                                    "
                                    :src="album.cover_image_url"
                                    :alt="album.cover_alt"
                                    loading="lazy"
                                    @error="albumImageFailures[album.id] = true"
                                />
                                <div
                                    v-else
                                    class="browser-image-error"
                                    role="img"
                                    :aria-label="
                                        album.cover_alt + '，封面暂不可用'
                                    "
                                >
                                    <ImageOff :size="28" aria-hidden="true" />
                                </div>
                                <span class="album-count"
                                    >{{ album.public_photos_count }} 张</span
                                >
                            </div>
                            <div class="album-card-copy">
                                <h3>{{ album.title }}</h3>
                                <time>{{
                                    formatDate(album.published_at)
                                }}</time>
                                <p
                                    v-if="album.category_summary"
                                    class="album-category"
                                >
                                    {{ album.category_summary }}
                                </p>
                            </div>
                            <p
                                v-if="album.description"
                                class="album-description"
                            >
                                {{ album.description }}
                            </p></Link
                        >
                    </div>
                </section>
                <section
                    v-if="hasPhotos"
                    class="topic-section"
                    aria-labelledby="topic-photos-heading"
                >
                    <div class="topic-section-heading">
                        <div>
                            <h2 id="topic-photos-heading">精选图片</h2>
                            <p>按后台配置顺序展示公开图片。</p>
                        </div>
                        <Link href="/photos"
                            >浏览图库 <span aria-hidden="true">→</span></Link
                        >
                    </div>
                    <div class="browser-photo-grid">
                        <Link
                            v-for="photo in topicDetail.photos.data"
                            :key="photo.uuid"
                            :href="photo.url"
                            class="browser-photo-card"
                            :aria-label="`查看图片：${photo.title}`"
                            ><div class="browser-photo">
                                <img
                                    v-if="
                                        photo.image_url &&
                                        !photoImageFailures[photo.id]
                                    "
                                    :src="photo.image_url"
                                    :alt="photo.alt"
                                    loading="lazy"
                                    @error="photoImageFailures[photo.id] = true"
                                />
                                <div
                                    v-else
                                    class="browser-image-error"
                                    role="img"
                                    :aria-label="photo.title + '，预览暂不可用'"
                                >
                                    <ImageOff :size="28" aria-hidden="true" />
                                </div>
                                <span
                                    class="browser-entry-arrow"
                                    aria-hidden="true"
                                    >↗</span
                                >
                            </div>
                            <div class="browser-card-copy">
                                <h3>{{ photo.title }}</h3>
                                <p class="browser-date">
                                    {{ formatDate(photo.event_date) }}
                                </p>
                                <p
                                    v-if="photo.category_summary"
                                    class="browser-category"
                                >
                                    {{ photo.category_summary }}
                                </p>
                                <div
                                    v-if="photo.tags.length"
                                    class="browser-tags"
                                >
                                    <span
                                        v-for="tag in photo.tags.slice(0, 3)"
                                        :key="tag.id"
                                        >{{ tag.name }}</span
                                    >
                                </div>
                            </div></Link
                        >
                    </div>
                </section>
            </template>
            <section v-else class="browser-empty-state topic-section">
                <Images :size="36" aria-hidden="true" />
                <h2>这个专题还没有可展示内容</h2>
                <p>
                    后台为该专题配置公开相册或精选图片后，这里会展示专题内容。
                </p>
                <div>
                    <Link href="/albums" class="browser-primary">查看相册</Link
                    ><Link href="/photos" class="browser-outline"
                        >浏览图库</Link
                    >
                </div>
            </section>
        </div>
    </main>
</template>
