<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import PhotoDetail from '@/pages/Photos/Show.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    ImageOff,
    LoaderCircle,
    X,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import '../../../css/archive-browser.css';
import './Albums.css';
import './Slideshow.css';

type Detail = InstanceType<typeof PhotoDetail>['$props']['photoDetail'];
interface AlbumDetailPayload {
    site: Detail['site'];
    navigation: Detail['navigation'];
    seo: SeoPayload;
    album: {
        title: string;
        slug: string;
        description: string | null;
        published_at: string | null;
    };
    filters: { sort: string };
    filter_options: { sorts: { value: string; label: string }[] };
    photos: {
        data: {
            id: number;
            uuid: string;
            title: string;
            image_url: string | null;
            alt: string;
        }[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
        links: { prev: string | null; next: string | null };
    };
    viewer: {
        position: number;
        total: number;
        previous_url: string | null;
        next_url: string | null;
    };
    current_photo: Detail | null;
}
const props = defineProps<{ albumDetail: AlbumDetailPayload }>();
const pending = ref(false);
const zoomDialog = ref<HTMLDialogElement | null>(null);
const zoomImageFailed = ref(false);
watch(
    () => props.albumDetail.current_photo?.photo.uuid,
    () => {
        zoomImageFailed.value = false;
    },
);
const closeZoom = () => {
    zoomDialog.value?.close();
    document
        .querySelector<HTMLElement>('.album-photo-detail .detail-viewer img')
        ?.focus({ preventScroll: true });
};
const failure = ref('');
const imageFailures = ref<Record<number, boolean>>({});
const filmstrip = ref<HTMLElement | null>(null);
const revealThumbnail = () => {
    const strip = filmstrip.value;
    const current = strip?.querySelector<HTMLElement>('[aria-current="true"]');
    if (strip && current)
        strip.scrollLeft +=
            current.getBoundingClientRect().left -
            strip.getBoundingClientRect().left -
            (strip.clientWidth - current.clientWidth) / 2;
};
watch(() => props.albumDetail.viewer.position, revealThumbnail, {
    flush: 'post',
});
const visit = (url: string | null) => {
    if (!url || pending.value) return;
    pending.value = true;
    failure.value = '';
    router.get(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                failure.value = '切换失败，请重试。';
            },
            onFinish: () => {
                pending.value = false;
            },
        },
    );
};
const slideUrl = (position: number) =>
    `/albums/${props.albumDetail.album.slug}?${new URLSearchParams({ sort: props.albumDetail.filters.sort, slide: String(position) })}`;
const sortChanged = (event: Event) => {
    const sort = (event.target as HTMLSelectElement).value;
    visit(
        `/albums/${props.albumDetail.album.slug}?${new URLSearchParams({ sort })}`,
    );
};
const onKey = (event: KeyboardEvent) => {
    if (
        event.defaultPrevented ||
        event.altKey ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey
    )
        return;
    const target = event.target as HTMLElement;
    const viewingZoom = zoomDialog.value?.open;
    if (
        !viewingZoom &&
        (target.closest(
            'input, textarea, select, button, a, [contenteditable="true"], [role="dialog"]',
        ) ||
            document.querySelector('dialog[open], [role="dialog"]'))
    )
        return;
    const url =
        event.key === 'ArrowLeft'
            ? props.albumDetail.viewer.previous_url
            : event.key === 'ArrowRight'
              ? props.albumDetail.viewer.next_url
              : null;
    if (url) {
        event.preventDefault();
        visit(url);
    }
};
let touch: { x: number; y: number } | null = null;
const touchStart = (event: TouchEvent) => {
    if (
        !(event.target as HTMLElement).closest(
            '.detail-viewer, .album-zoom-dialog',
        ) ||
        (event.target as HTMLElement).closest('button') ||
        event.touches.length !== 1
    ) {
        touch = null;
        return;
    }
    touch = { x: event.touches[0].clientX, y: event.touches[0].clientY };
};
const touchEnd = (event: TouchEvent) => {
    if (!touch || !event.changedTouches.length) return;
    const dx = event.changedTouches[0].clientX - touch.x;
    const dy = event.changedTouches[0].clientY - touch.y;
    touch = null;
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5)
        visit(
            dx < 0
                ? props.albumDetail.viewer.next_url
                : props.albumDetail.viewer.previous_url,
        );
};
onMounted(() => {
    window.addEventListener('keydown', onKey);
    revealThumbnail();
});
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <SeoHead :seo="albumDetail.seo" />
    <main
        class="archive-browser archive-albums album-slideshow"
        @touchstart.passive="touchStart"
        @touchend.passive="touchEnd"
        @touchcancel="touch = null"
    >
        <PublicHeader
            :navigation="albumDetail.navigation"
            :site="albumDetail.site"
            theme="afa"
        />
        <div class="album-slideshow-heading">
            <Link href="/albums" class="album-back"
                ><ArrowLeft :size="17" aria-hidden="true" />返回相册列表</Link
            >
            <div class="album-slideshow-title">
                <div>
                    <h1>{{ albumDetail.album.title }}</h1>
                    <details
                        v-if="albumDetail.album.description"
                        class="album-description-disclosure"
                    >
                        <summary>相册简介</summary>
                        <p>{{ albumDetail.album.description }}</p>
                    </details>
                </div>
                <label class="browser-sort-control"
                    >排序<select
                        :value="albumDetail.filters.sort"
                        :disabled="pending"
                        aria-label="相册图片排序"
                        @change="sortChanged"
                    >
                        <option
                            v-for="sort in albumDetail.filter_options.sorts"
                            :key="sort.value"
                            :value="sort.value"
                        >
                            {{ sort.label }}
                        </option>
                    </select></label
                >
            </div>
            <p v-if="failure" role="alert">{{ failure }}</p>
        </div>
        <PhotoDetail
            v-if="albumDetail.current_photo"
            :key="albumDetail.current_photo.photo.uuid"
            :photo-detail="albumDetail.current_photo"
            embedded
            :share-album="{
                slug: albumDetail.album.slug,
                title: albumDetail.album.title,
            }"
            :aria-busy="pending"
            @zoom="zoomDialog?.showModal()"
        >
            <template #viewer-position>
                <div class="album-position-controls">
                    <button
                        type="button"
                        aria-label="工具栏上一张"
                        :disabled="pending || !albumDetail.viewer.previous_url"
                        @click="visit(albumDetail.viewer.previous_url)"
                    >
                        <ChevronLeft :size="18" aria-hidden="true" />
                    </button>
                    <p role="status" aria-live="polite" aria-atomic="true">
                        <strong>{{ albumDetail.viewer.position }}</strong> /
                        {{ albumDetail.viewer.total }} 张<span
                            v-if="pending"
                            class="album-loading"
                            ><LoaderCircle
                                :size="14"
                                aria-hidden="true"
                            />正在切换</span
                        >
                    </p>
                    <button
                        type="button"
                        aria-label="工具栏下一张"
                        :disabled="pending || !albumDetail.viewer.next_url"
                        @click="visit(albumDetail.viewer.next_url)"
                    >
                        <ChevronRight :size="18" aria-hidden="true" />
                    </button>
                </div>
            </template>
            <template #viewer-controls>
                <button
                    type="button"
                    class="album-slide-arrow album-slide-previous"
                    aria-label="相册上一张"
                    :disabled="pending || !albumDetail.viewer.previous_url"
                    @click="visit(albumDetail.viewer.previous_url)"
                >
                    <ChevronLeft :size="24" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="album-slide-arrow album-slide-next"
                    aria-label="相册下一张"
                    :disabled="pending || !albumDetail.viewer.next_url"
                    @click="visit(albumDetail.viewer.next_url)"
                >
                    <ChevronRight :size="24" aria-hidden="true" />
                </button>
            </template>
            <template #filmstrip>
                <nav
                    ref="filmstrip"
                    class="album-filmstrip"
                    aria-label="相册图片导航"
                >
                    <button
                        v-for="(photo, index) in albumDetail.photos.data"
                        :key="photo.uuid"
                        type="button"
                        :aria-label="`查看第${(albumDetail.photos.meta.from ?? 1) + index}张：${photo.title}`"
                        :aria-current="
                            photo.uuid === albumDetail.current_photo.photo.uuid
                                ? 'true'
                                : undefined
                        "
                        :disabled="pending"
                        @click="
                            visit(
                                slideUrl(
                                    (albumDetail.photos.meta.from ?? 1) + index,
                                ),
                            )
                        "
                    >
                        <img
                            v-if="photo.image_url && !imageFailures[photo.id]"
                            :src="photo.image_url"
                            :alt="photo.alt"
                            loading="lazy"
                            @error="imageFailures[photo.id] = true"
                        /><ImageOff v-else :size="20" aria-hidden="true" />
                    </button>
                </nav>
                <ArchivePagination
                    v-if="albumDetail.photos.meta.last_page > 1"
                    :meta="albumDetail.photos.meta"
                    :links="{
                        prev:
                            albumDetail.photos.meta.current_page > 1
                                ? slideUrl(
                                      (albumDetail.photos.meta.current_page -
                                          2) *
                                          albumDetail.photos.meta.per_page +
                                          1,
                                  )
                                : null,
                        next:
                            albumDetail.photos.meta.current_page <
                            albumDetail.photos.meta.last_page
                                ? slideUrl(
                                      albumDetail.photos.meta.current_page *
                                          albumDetail.photos.meta.per_page +
                                          1,
                                  )
                                : null,
                    }"
                    label="相册缩略图分页"
                />
            </template>
        </PhotoDetail>
        <dialog
            ref="zoomDialog"
            class="detail-zoom-dialog album-zoom-dialog"
            aria-label="相册放大浏览"
            :aria-busy="pending"
            @cancel.prevent="closeZoom"
            @click="$event.target === $event.currentTarget && closeZoom()"
        >
            <button
                type="button"
                class="detail-zoom-close"
                aria-label="关闭放大查看"
                @click="closeZoom"
            >
                <X :size="24" aria-hidden="true" />
            </button>
            <img
                v-if="
                    albumDetail.current_photo?.photo.image_url &&
                    !zoomImageFailed
                "
                :src="albumDetail.current_photo.photo.image_url"
                :alt="albumDetail.current_photo.photo.alt"
                @error="zoomImageFailed = true"
            />
            <div v-else class="detail-image-error">
                这张图片暂时无法显示，仍可切换其他图片。
            </div>
            <button
                type="button"
                class="album-slide-arrow album-slide-previous"
                aria-label="放大视图上一张"
                :disabled="pending || !albumDetail.viewer.previous_url"
                @click="visit(albumDetail.viewer.previous_url)"
            >
                <ChevronLeft :size="24" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="album-slide-arrow album-slide-next"
                aria-label="放大视图下一张"
                :disabled="pending || !albumDetail.viewer.next_url"
                @click="visit(albumDetail.viewer.next_url)"
            >
                <ChevronRight :size="24" aria-hidden="true" />
            </button>
            <p aria-live="polite">
                {{ albumDetail.viewer.position }} /
                {{ albumDetail.viewer.total }} ·
                {{ albumDetail.current_photo?.photo.title }}
            </p>
        </dialog>
        <div v-if="!albumDetail.current_photo" class="album-slideshow-empty">
            <ImageOff :size="32" aria-hidden="true" />
            <p>这个相册暂时没有可浏览的公开图片。</p>
            <Link href="/albums">浏览其他相册</Link>
        </div>
    </main>
</template>
