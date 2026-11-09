<script setup lang="ts">
import CommentContent from '@/components/CommentContent.vue';
import CommentEmojiPicker from '@/components/CommentEmojiPicker.vue';
import CommentThreadList from '@/components/CommentThreadList.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import SharePanel from '@/components/SharePanel.vue';
import SimilarPhotoGrid from '@/components/SimilarPhotoGrid.vue';
import type { SeoPayload } from '@/types';
import type { ThreadComment } from '@/types/comments';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    FileText,
    Heart,
    ImageOff,
    Images,
    MessageCircle,
    PencilLine,
    Send,
    ThumbsUp,
    X,
} from 'lucide-vue-next';

import { gsap } from 'gsap';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import './Show.css';

interface NavigationItem {
    label: string;
    url: string;
}

interface CategorySummary {
    id: number;
    name: string;
    root_name: string | null;
    root_slug: string | null;
    url: string;
}

interface TagSummary {
    id: number;
    name: string;
    url: string;
}

interface AlbumSummary {
    id: number;
    title: string;
    slug: string;
    url: string;
}

interface SourceSummary {
    source_url: string;
}

interface InteractionState {
    can_interact: boolean;
    is_blocked: boolean;
    blocked_reason: string | null;
    is_favorited: boolean;
    is_liked: boolean;
    likes_count: number;
}

interface PhotoDetailCard {
    id: number;
    uuid: string;
    title: string;
    description: string | null;
    submission: {
        source_note: string | null;
        credit: { name: string; url: string | null } | null;
    } | null;
    url: string;
    image_url: string | null;
    alt: string;
    taken_at: string | null;
    event_date: string | null;
    published_at: string | null;
    copyright_status: {
        value: string;
        label: string;
    };
    width: number | null;
    height: number | null;
    mime_type: string | null;
    file_size_label: string | null;
    categories: CategorySummary[];
    tags: TagSummary[];
    albums: AlbumSummary[];
    source: SourceSummary | null;
    interactions: InteractionState;
}

interface ContextSummary {
    type: 'gallery' | 'album' | 'topic';
    title: string;
    slug: string | null;
    return_url: string;
}

interface PhotoCard {
    id: number;
    uuid: string;
    title: string;
    url: string;
    image_url: string | null;
    alt: string;
    event_date: string | null;
    published_at: string | null;
}

interface CommentsPayload {
    can_submit: boolean;
    can_report: boolean;
    is_blocked: boolean;
    blocked_reason: string | null;
    total: number;
    data: ThreadComment[];
    has_more: boolean;
    pending: { id: number; content: string }[];
}
interface PhotoDetailPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    photo: PhotoDetailCard;
    context: ContextSummary;
    adjacent: {
        previous: PhotoCard | null;
        next: PhotoCard | null;
    };
    related: {
        data: PhotoCard[];
    };
    similar: { data: PhotoCard[] };
    comments: CommentsPayload;
}

const props = defineProps<{
    photoDetail: PhotoDetailPayload;
    embedded?: boolean;
    shareAlbum?: { slug: string; title: string };
}>();

const emit = defineEmits<{ zoom: [] }>();

type ComposerMode = 'discussion' | 'correction' | null;
type ReportReason = 'spam' | 'abuse' | 'copyright' | 'misleading' | 'other';

const pendingDiscussions = ref(props.photoDetail.comments.pending ?? []);
const imageFailed = ref(false);
let imageTween: gsap.core.Tween | undefined;
const revealImage = (event: Event) => {
    if (
        props.embedded &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        imageTween?.revert();
        imageTween = gsap.fromTo(
            event.target,
            { opacity: 0, y: 6 },
            {
                opacity: 1,
                y: 0,
                duration: 0.24,
                ease: 'power2.out',
                clearProps: 'opacity,transform',
            },
        );
    }
};
const relatedImageFailures = ref<Record<number, boolean>>({});
const favoritePending = ref(false);
const likePending = ref(false);

const shareStatus = ref<string | null>(null);

const activeReportId = ref<number | null>(null);
const reportReason = ref<ReportReason>('spam');
const reportDetails = ref('');
const reportPending = ref(false);
const reportStatus = ref<string | null>(null);
const activeComposer = ref<ComposerMode>(null);
const commentContent = ref('');
const correctionField = ref('other');
const correctionSuggestedValue = ref('');
const correctionEvidenceUrl = ref('');
const correctionContent = ref('');
const commentPending = ref(false);
const correctionPending = ref(false);
const commentStatus = ref<string | null>(null);
const correctionStatus = ref<string | null>(null);
const correctionFields = [
    { value: 'title', label: '标题' },
    { value: 'description', label: '说明' },
    { value: 'taken_at', label: '拍摄时间' },
    { value: 'event_date', label: '事件日期' },
    { value: 'competition', label: '赛事' },
    { value: 'team', label: '球队' },
    { value: 'people', label: '人物同框' },
    { value: 'source', label: '来源' },
    { value: 'copyright', label: '版权备注' },
    { value: 'category', label: '分类' },
    { value: 'tag', label: '标签' },
    { value: 'other', label: '其他' },
];
const interactions = ref<InteractionState>({
    ...props.photoDetail.photo.interactions,
});

watch(
    () => props.photoDetail.photo.interactions,
    (value) => {
        interactions.value = { ...value };
    },
    { deep: true },
);

const favoriteUrl = computed(
    () => '/photos/' + props.photoDetail.photo.uuid + '/favorite',
);
const likeUrl = computed(
    () => '/photos/' + props.photoDetail.photo.uuid + '/like',
);
const commentUrl = computed(
    () => '/photos/' + props.photoDetail.photo.uuid + '/comments',
);
const correctionUrl = computed(
    () => '/photos/' + props.photoDetail.photo.uuid + '/corrections',
);
const reportUrl = (commentId: number) => '/comments/' + commentId + '/reports';
const reportReasons: Array<{ value: ReportReason; label: string }> = [
    { value: 'spam', label: '垃圾广告' },
    { value: 'abuse', label: '攻击辱骂' },
    { value: 'copyright', label: '版权或来源问题' },
    { value: 'misleading', label: '误导或不实信息' },
    { value: 'other', label: '其他' },
];
const hasMeta = computed(
    () =>
        props.photoDetail.photo.width ||
        props.photoDetail.photo.height ||
        props.photoDetail.photo.mime_type ||
        props.photoDetail.photo.file_size_label,
);
const hasRelated = computed(() => props.photoDetail.related.data.length > 0);
const contextLabel = computed(() => {
    if (props.photoDetail.context.type === 'album') {
        return '返回相册';
    }

    if (props.photoDetail.context.type === 'topic') {
        return '返回专题';
    }

    return '返回图库';
});

const formatDate = (date: string | null) => date ?? '待补充';

const clearShareStatus = () => {
    window.setTimeout(() => {
        shareStatus.value = null;
    }, 1800);
};

const clearReportStatus = () => {
    window.setTimeout(() => {
        reportStatus.value = null;
    }, 2200);
};

const csrfCookie = () => {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return cookie ? decodeURIComponent(cookie) : '';
};

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const csrfHeaders = (): Record<string, string> => {
    const cookie = csrfCookie();

    if (cookie) {
        return { 'X-XSRF-TOKEN': cookie };
    }

    return { 'X-CSRF-TOKEN': csrfToken() };
};

const refreshCsrfToken = async (): Promise<void> => {
    await fetch(window.location.href, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'text/html, application/xhtml+xml',
            'X-Requested-With': 'XMLHttpRequest',
        },
        cache: 'no-store',
    });
};

const interactionRequest = async <T,>(
    url: string,
    method: 'POST' | 'DELETE',
    body?: Record<string, string>,
    retried = false,
): Promise<T> => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...csrfHeaders(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (response.status === 419 && !retried) {
        await refreshCsrfToken();

        return interactionRequest<T>(url, method, body, true);
    }

    if (response.status === 401 || response.status === 419) {
        router.visit('/login');
        throw new Error('auth_required');
    }

    if (!response.ok) {
        try {
            const error = (await response.json()) as { message?: string };
            throw new Error(error.message ?? 'interaction_failed');
        } catch (error) {
            if (error instanceof Error && error.message !== '') {
                throw error;
            }

            throw new Error('interaction_failed');
        }
    }

    return (await response.json()) as T;
};

const particleEffects = new Map<gsap.core.Timeline, HTMLElement[]>();
const burst = (button: HTMLElement | null) => {
    if (
        !button ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    )
        return;
    const bounds = (
        button.querySelector('svg') ?? button
    ).getBoundingClientRect();
    const layer = document.createElement('div');
    layer.className = 'interaction-confetti-layer';
    layer.setAttribute('aria-hidden', 'true');
    document.body.appendChild(layer);
    const colors = [
        '#98cef0',
        '#90b4ce',
        '#ba9f6c',
        '#042240',
        '#526779',
        '#ffffff',
    ];
    const shapes = ['rectangle', 'square', 'heart', 'star', 'five-star'];
    const particles = Array.from({ length: 28 }, () => {
        const paper = document.createElement('span');
        const shape = shapes[Math.floor(Math.random() * shapes.length)];
        const color = colors[Math.floor(Math.random() * colors.length)];
        const size =
            shape === 'heart' || shape.includes('star')
                ? 11 + Math.random() * 4
                : 7 + Math.random() * 4;
        paper.className = `interaction-confetti interaction-confetti-${shape}`;
        paper.style.left = `${bounds.left + bounds.width / 2}px`;
        paper.style.top = `${bounds.top + bounds.height / 2}px`;
        paper.style.width = `${shape === 'rectangle' ? size * 0.6 : size}px`;
        paper.style.height = `${shape === 'rectangle' ? size * 1.35 : size}px`;
        paper.style.backgroundColor = color;
        if (color === '#ffffff')
            paper.style.filter = 'drop-shadow(0 0 1px #526779)';
        layer.appendChild(paper);
        return paper;
    });
    const motion = gsap.timeline({
        onComplete: () => {
            layer.remove();
            particleEffects.delete(motion);
        },
    });
    gsap.set(particles, { xPercent: -50, yPercent: -50, scale: 0.5 });
    particles.forEach((paper) => {
        const angle = Math.random() * Math.PI * 2;
        const distance = 45 + Math.random() * 70;
        const duration = 0.32 + Math.random() * 0.24;
        const start = Math.random() * 0.025;
        motion
            .to(
                paper,
                {
                    x: Math.cos(angle) * distance,
                    y: Math.sin(angle) * distance,
                    scale: 1,
                    duration,
                    ease: 'power3.out',
                },
                start,
            )
            .to(
                paper,
                {
                    opacity: 0,
                    duration: 0.18,
                    ease: 'power1.in',
                },
                start + duration - 0.12,
            );
    });
    particleEffects.set(motion, [layer]);
};

const toggleFavorite = async (event: MouseEvent) => {
    const button = event.currentTarget as HTMLElement | null;
    if (favoritePending.value) {
        return;
    }

    if (!interactions.value.can_interact && interactions.value.blocked_reason) {
        shareStatus.value = interactions.value.blocked_reason;
        clearShareStatus();
        return;
    }

    if (!interactions.value.can_interact) {
        router.visit('/login');
        return;
    }
    favoritePending.value = true;
    const wasFavorited = interactions.value.is_favorited;
    interactions.value.is_favorited = !wasFavorited;

    try {
        const result = await interactionRequest<{ is_favorited: boolean }>(
            favoriteUrl.value,
            wasFavorited ? 'DELETE' : 'POST',
        );

        interactions.value.is_favorited = result.is_favorited;
        if (!wasFavorited && result.is_favorited) burst(button);
    } catch {
        interactions.value.is_favorited = wasFavorited;
        shareStatus.value = '收藏状态未保存，请重试。';
        clearShareStatus();
    } finally {
        favoritePending.value = false;
    }
};

const toggleLike = async (event: MouseEvent) => {
    const button = event.currentTarget as HTMLElement | null;
    if (likePending.value) {
        return;
    }

    if (!interactions.value.can_interact) {
        if (interactions.value.blocked_reason) {
            shareStatus.value = interactions.value.blocked_reason;
            clearShareStatus();
            return;
        }

        router.visit('/login');
        return;
    }

    likePending.value = true;
    const wasLiked = interactions.value.is_liked;
    const previousLikesCount = interactions.value.likes_count;
    interactions.value.is_liked = !wasLiked;
    interactions.value.likes_count = Math.max(
        0,
        previousLikesCount + (wasLiked ? -1 : 1),
    );

    try {
        const result = await interactionRequest<{
            is_liked: boolean;
            likes_count: number;
        }>(likeUrl.value, wasLiked ? 'DELETE' : 'POST');

        interactions.value.is_liked = result.is_liked;
        interactions.value.likes_count = result.likes_count;
        if (!wasLiked && result.is_liked) burst(button);
    } catch {
        interactions.value.is_liked = wasLiked;
        interactions.value.likes_count = previousLikesCount;
        shareStatus.value = '点赞状态未保存，请重试。';
        clearShareStatus();
    } finally {
        likePending.value = false;
    }
};

const clearCommentStatus = () => {
    window.setTimeout(() => {
        commentStatus.value = null;
        correctionStatus.value = null;
    }, 2200);
};

const openComposer = (mode: Exclude<ComposerMode, null>) => {
    if (!props.photoDetail.comments.can_submit) {
        if (props.photoDetail.comments.blocked_reason) {
            commentStatus.value = props.photoDetail.comments.blocked_reason;
            clearCommentStatus();
            return;
        }

        router.visit('/login');
        return;
    }

    activeComposer.value = activeComposer.value === mode ? null : mode;
};

const openReport = (commentId: number) => {
    if (!props.photoDetail.comments.can_report) {
        if (props.photoDetail.comments.blocked_reason) {
            reportStatus.value = props.photoDetail.comments.blocked_reason;
            clearReportStatus();
            return;
        }

        router.visit('/login');
        return;
    }

    activeReportId.value =
        activeReportId.value === commentId ? null : commentId;
    if (activeReportId.value !== null) {
        void nextTick(() =>
            document
                .getElementById('comment-report-form')
                ?.scrollIntoView({ block: 'center' }),
        );
    }
};

const submitDiscussion = async () => {
    if (commentPending.value) {
        return;
    }

    const content = commentContent.value.trim();

    if (
        content.length < 2 &&
        !/[\u{1F000}-\u{1FAFF}\u2600-\u27BF]/u.test(content)
    ) {
        commentStatus.value = '评论至少需要 2 个字。';
        clearCommentStatus();
        return;
    }

    commentPending.value = true;

    try {
        const result = await interactionRequest<{
            message: string;
            id: number;
        }>(commentUrl.value, 'POST', { content });
        pendingDiscussions.value.push({ id: result.id, content });
        commentContent.value = '';
        activeComposer.value = null;
        commentStatus.value = result.message;
    } catch {
        commentStatus.value = '评论提交失败，请稍后重试。';
    } finally {
        commentPending.value = false;
        clearCommentStatus();
    }
};

const submitReport = async (commentId: number) => {
    if (reportPending.value) {
        return;
    }

    reportPending.value = true;

    try {
        const result = await interactionRequest<{ message: string }>(
            reportUrl(commentId),
            'POST',
            {
                reason: reportReason.value,
                details: reportDetails.value.trim(),
            },
        );
        reportReason.value = 'spam';
        reportDetails.value = '';
        activeReportId.value = null;
        reportStatus.value = result.message;
    } catch (error) {
        reportStatus.value =
            error instanceof Error && error.message !== 'interaction_failed'
                ? error.message
                : '举报提交失败，请稍后重试。';
    } finally {
        reportPending.value = false;
        clearReportStatus();
    }
};

const submitCorrection = async () => {
    if (correctionPending.value) {
        return;
    }

    const content = correctionContent.value.trim();

    if (content.length < 2) {
        correctionStatus.value = '补充 / 纠错说明至少需要 2 个字。';
        clearCommentStatus();
        return;
    }

    correctionPending.value = true;

    try {
        const result = await interactionRequest<{ message: string }>(
            correctionUrl.value,
            'POST',
            {
                correction_field: correctionField.value,
                suggested_value: correctionSuggestedValue.value.trim(),
                evidence_url: correctionEvidenceUrl.value.trim(),
                content,
            },
        );
        correctionField.value = 'other';
        correctionSuggestedValue.value = '';
        correctionEvidenceUrl.value = '';
        correctionContent.value = '';
        activeComposer.value = null;
        correctionStatus.value = result.message;
    } catch {
        correctionStatus.value = '补充 / 纠错提交失败，请稍后重试。';
    } finally {
        correctionPending.value = false;
        clearCommentStatus();
    }
};

// 所有屏幕均以看图为主，完整资料由用户主动展开。
const detailRoot = ref<HTMLElement | null>(null);
const metadataContent = ref<HTMLElement | null>(null);
const zoomDialog = ref<HTMLDialogElement | null>(null);
const zoomPending = ref(false);
const openZoom = () => {
    if (props.embedded) emit('zoom');
    else zoomDialog.value?.showModal();
};
const stepZoom = (direction: 'previous' | 'next') => {
    const photo = props.photoDetail.adjacent[direction];
    if (
        !photo ||
        zoomPending.value ||
        favoritePending.value ||
        likePending.value ||
        commentPending.value ||
        correctionPending.value ||
        reportPending.value
    )
        return;
    zoomPending.value = true;
    router.get(
        photo.url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                zoomPending.value = false;
            },
        },
    );
};
const metadataExpanded = ref(false);
const metadataVisible = computed(() => metadataExpanded.value);
let detailMotion: gsap.MatchMedia | undefined;
let metadataTween: gsap.core.Tween | undefined;
const toggleMetadata = async () => {
    metadataExpanded.value = !metadataExpanded.value;
    metadataTween?.revert();
    await nextTick();
    if (
        metadataExpanded.value &&
        metadataContent.value &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        metadataTween = gsap.fromTo(
            metadataContent.value,
            { opacity: 0, y: 4 },
            {
                opacity: 1,
                y: 0,
                duration: 0.16,
                clearProps: 'opacity,transform',
            },
        );
    }
};
onMounted(() => {
    detailMotion = gsap.matchMedia();
    detailMotion.add(
        '(prefers-reduced-motion: no-preference)',
        () => {
            if (props.embedded) return;
            gsap.from('.detail-title, .detail-actions', {
                y: 6,
                duration: 0.24,
                stagger: 0.04,
                clearProps: 'transform',
            });
        },
        detailRoot.value ?? undefined,
    );
});
onUnmounted(() => {
    particleEffects.forEach((particles, tween) => {
        tween.kill();
        particles.forEach((dot) => dot.remove());
    });
    particleEffects.clear();
    imageTween?.revert();
    detailMotion?.revert();
    metadataTween?.revert();
});
watch(
    () => props.photoDetail.photo.uuid,
    () => {
        metadataTween?.revert();
        pendingDiscussions.value = props.photoDetail.comments.pending ?? [];
        metadataExpanded.value = false;
        imageFailed.value = false;
        relatedImageFailures.value = {};
        activeComposer.value = null;
        commentContent.value = '';
        correctionContent.value = '';
        correctionField.value = 'other';
        correctionSuggestedValue.value = '';
        correctionEvidenceUrl.value = '';
        activeReportId.value = null;
        reportDetails.value = '';
        commentStatus.value = null;
        correctionStatus.value = null;
        reportStatus.value = null;
        shareStatus.value = null;
    },
);
</script>
<template>
    <SeoHead v-if="!embedded" :seo="photoDetail.seo" />
    <component
        :is="embedded ? 'div' : 'main'"
        ref="detailRoot"
        class="archive-detail"
        :class="{ 'album-photo-detail': embedded }"
    >
        <PublicHeader
            v-if="!embedded"
            :navigation="photoDetail.navigation"
            :site="photoDetail.site"
            theme="afa"
        />
        <section class="detail-container">
            <div v-if="!embedded" class="detail-context">
                <Link :href="photoDetail.context.return_url"
                    ><ArrowLeft aria-hidden="true" />{{ contextLabel }}</Link
                ><span>图片档案</span>
            </div>
            <div class="detail-layout">
                <div class="detail-viewer-column">
                    <figure class="detail-viewer">
                        <img
                            v-if="photoDetail.photo.image_url && !imageFailed"
                            :src="photoDetail.photo.image_url"
                            :alt="photoDetail.photo.alt"
                            role="button"
                            tabindex="0"
                            :aria-label="`放大查看：${photoDetail.photo.alt}`"
                            @click="openZoom"
                            @keydown.enter.prevent="openZoom"
                            @keydown.space.prevent="openZoom"
                            @load="revealImage"
                            @error="imageFailed = true"
                        />
                        <div
                            v-else
                            class="detail-image-error"
                            role="img"
                            :aria-label="
                                photoDetail.photo.alt + '，预览暂不可用'
                            "
                        >
                            <ImageOff aria-hidden="true" />
                            <p>图片暂时无法显示</p>
                            <small>档案资料与其他浏览入口仍可使用。</small>
                        </div>
                        <slot name="viewer-controls" />
                    </figure>
                    <div class="detail-actions">
                        <slot name="viewer-position" />
                        <button
                            type="button"
                            :aria-pressed="interactions.is_favorited"
                            :aria-busy="favoritePending"
                            :disabled="favoritePending"
                            @click="toggleFavorite"
                        >
                            <Heart
                                :class="{
                                    'fill-current': interactions.is_favorited,
                                }"
                                aria-hidden="true"
                            />{{
                                interactions.is_favorited ? '已收藏' : '收藏'
                            }}
                        </button>
                        <button
                            type="button"
                            :aria-label="`点赞，${interactions.likes_count} 次`"
                            :aria-pressed="interactions.is_liked"
                            :aria-busy="likePending"
                            :disabled="likePending"
                            @click="toggleLike"
                        >
                            <ThumbsUp
                                :class="{
                                    'fill-current': interactions.is_liked,
                                }"
                                aria-hidden="true"
                            /><span>{{ interactions.likes_count }}</span>
                        </button>
                        <SharePanel
                            theme="archive"
                            type="photo"
                            :target-key="photoDetail.photo.uuid"
                            :title="photoDetail.photo.title"
                            :blocked-reason="interactions.blocked_reason"
                            :album="shareAlbum"
                        />
                    </div>
                    <slot name="filmstrip" />
                    <p v-if="shareStatus" role="status" class="detail-notice">
                        {{ shareStatus }}
                    </p>
                    <nav
                        v-if="
                            !embedded &&
                            (photoDetail.adjacent.previous ||
                                photoDetail.adjacent.next)
                        "
                        class="detail-adjacent"
                        aria-label="上下张图片"
                    >
                        <Link
                            v-if="photoDetail.adjacent.previous"
                            :href="photoDetail.adjacent.previous.url"
                            ><ChevronLeft aria-hidden="true" /><span
                                ><small>上一张</small
                                >{{ photoDetail.adjacent.previous.title }}</span
                            ></Link
                        >
                        <Link
                            v-if="photoDetail.adjacent.next"
                            :href="photoDetail.adjacent.next.url"
                            class="detail-next"
                            ><span
                                ><small>下一张</small
                                >{{ photoDetail.adjacent.next.title }}</span
                            ><ChevronRight aria-hidden="true"
                        /></Link>
                    </nav>
                </div>
                <aside class="detail-metadata">
                    <div class="detail-title">
                        <component :is="embedded ? 'h2' : 'h1'">{{
                            photoDetail.photo.title
                        }}</component>
                    </div>
                    <button
                        id="photo-metadata-toggle"
                        type="button"
                        class="detail-metadata-toggle"
                        :aria-expanded="metadataVisible"
                        aria-controls="photo-metadata-content"
                        @click="toggleMetadata"
                    >
                        <strong>图片资料</strong
                        ><span>{{ metadataVisible ? '收起' : '展开' }}</span
                        ><ChevronDown aria-hidden="true" />
                    </button>
                    <div
                        id="photo-metadata-content"
                        ref="metadataContent"
                        v-show="metadataVisible"
                        :class="{
                            'without-albums': !photoDetail.photo.albums.length,
                        }"
                    >
                        <p
                            v-if="photoDetail.photo.description"
                            class="detail-description"
                        >
                            {{ photoDetail.photo.description }}
                        </p>
                        <section aria-labelledby="photo-dates-heading">
                            <h2 id="photo-dates-heading">日期</h2>
                            <dl>
                                <dt>事件日期</dt>
                                <dd>
                                    {{
                                        formatDate(photoDetail.photo.event_date)
                                    }}
                                </dd>
                                <dt>拍摄时间</dt>
                                <dd>
                                    {{ formatDate(photoDetail.photo.taken_at) }}
                                </dd>
                                <dt>发布时间</dt>
                                <dd>
                                    {{
                                        formatDate(
                                            photoDetail.photo.published_at,
                                        )
                                    }}
                                </dd>
                            </dl>
                        </section>
                        <section
                            v-if="hasMeta"
                            aria-labelledby="photo-file-heading"
                        >
                            <h2 id="photo-file-heading">图片信息</h2>
                            <dl>
                                <template
                                    v-if="
                                        photoDetail.photo.width &&
                                        photoDetail.photo.height
                                    "
                                    ><dt>尺寸</dt>
                                    <dd>
                                        {{ photoDetail.photo.width }} ×
                                        {{ photoDetail.photo.height }}
                                    </dd></template
                                ><template v-if="photoDetail.photo.mime_type"
                                    ><dt>格式</dt>
                                    <dd>
                                        {{ photoDetail.photo.mime_type }}
                                    </dd></template
                                ><template
                                    v-if="photoDetail.photo.file_size_label"
                                    ><dt>文件大小</dt>
                                    <dd>
                                        {{ photoDetail.photo.file_size_label }}
                                    </dd></template
                                >
                            </dl>
                        </section>
                        <section aria-labelledby="photo-source-heading">
                            <h2 id="photo-source-heading">来源与版权</h2>
                            <dl>
                                <dt>版权状态</dt>
                                <dd>
                                    {{
                                        photoDetail.photo.copyright_status.label
                                    }}
                                </dd>
                                <template v-if="photoDetail.photo.submission"
                                    ><dt>投稿来源说明</dt>
                                    <dd>
                                        {{
                                            photoDetail.photo.submission
                                                .source_note
                                        }}
                                    </dd>
                                    <dt>图片投稿</dt>
                                    <dd>
                                        <a
                                            v-if="
                                                photoDetail.photo.submission
                                                    .credit?.url
                                            "
                                            :href="
                                                photoDetail.photo.submission
                                                    .credit.url
                                            "
                                            >{{
                                                photoDetail.photo.submission
                                                    .credit.name
                                            }}</a
                                        ><span v-else>{{
                                            photoDetail.photo.submission.credit
                                                ?.name ?? '匿名投稿'
                                        }}</span>
                                    </dd></template
                                >
                                <dt>图片来源</dt>
                                <dd>
                                    <a
                                        v-if="photoDetail.photo.source"
                                        :href="
                                            photoDetail.photo.source.source_url
                                        "
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        >来源链接
                                        <ExternalLink aria-hidden="true" /></a
                                    ><span v-else>待补充</span>
                                </dd>
                            </dl>
                        </section>
                        <section
                            v-if="photoDetail.photo.albums.length"
                            aria-labelledby="photo-albums-heading"
                        >
                            <h2 id="photo-albums-heading">所属相册</h2>
                            <Link
                                v-for="album in photoDetail.photo.albums"
                                :key="album.id"
                                :href="album.url"
                                class="detail-album"
                                ><Images aria-hidden="true" />{{
                                    album.title
                                }}</Link
                            >
                        </section>
                        <section
                            v-if="photoDetail.photo.categories.length"
                            class="detail-classifications"
                            aria-labelledby="photo-taxonomy-heading"
                        >
                            <h2 id="photo-taxonomy-heading">分类</h2>
                            <div class="detail-classification-list">
                                <div
                                    v-for="category in photoDetail.photo
                                        .categories"
                                    :key="category.id"
                                    class="detail-taxonomy"
                                >
                                    <span>{{ category.root_name }}</span
                                    ><Link :href="category.url">{{
                                        category.name
                                    }}</Link>
                                </div>
                            </div>
                        </section>
                        <section
                            v-if="photoDetail.photo.tags.length"
                            class="detail-tag-section"
                            aria-labelledby="photo-tags-heading"
                        >
                            <h2 id="photo-tags-heading">标签</h2>
                            <div class="detail-tags">
                                <Link
                                    v-for="tag in photoDetail.photo.tags"
                                    :key="tag.id"
                                    :href="tag.url"
                                    >{{ tag.name }}</Link
                                >
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
            <div class="detail-discussion-layout">
                <section
                    class="detail-comments detail-section"
                    aria-labelledby="photo-comments-heading"
                >
                    <div
                        class="mb-5 flex flex-wrap items-end justify-between gap-4"
                    >
                        <div>
                            <h2
                                id="photo-comments-heading"
                                class="text-2xl font-semibold"
                            >
                                评论与补充
                            </h2>
                            <p class="mt-2 text-sm text-[#526779]">
                                已公开 {{ photoDetail.comments.total }} 条评论
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center gap-2 rounded-sm border border-[#cbd8e2]/60 px-3 text-sm font-semibold transition hover:border-[#042240] hover:text-[#042240]"
                                :class="
                                    activeComposer === 'discussion'
                                        ? 'border-[#ba9f6c] bg-[#ba9f6c] text-[#042240]'
                                        : ''
                                "
                                @click="openComposer('discussion')"
                            >
                                <MessageCircle
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                                评论
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-10 items-center gap-2 rounded-sm border border-[#cbd8e2]/60 px-3 text-sm font-semibold transition hover:border-[#ba9f6c] hover:text-[#042240]"
                                :class="
                                    activeComposer === 'correction'
                                        ? 'border-[#ba9f6c] bg-[#ba9f6c] text-[#042240]'
                                        : ''
                                "
                                @click="openComposer('correction')"
                            >
                                <PencilLine
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                                补充 / 纠错
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="!photoDetail.comments.can_submit"
                        class="detail-notice mb-4"
                    >
                        登录后可以发表评论或提交图片信息补充 / 纠错。
                    </p>

                    <p
                        v-if="commentStatus"
                        class="mb-4 rounded-sm border border-[#042240]/30 bg-[#98cef0]/55 px-4 py-3 text-sm font-semibold text-[#042240]"
                    >
                        {{ commentStatus }}
                    </p>
                    <p
                        v-if="reportStatus"
                        class="mb-4 rounded-sm border border-[#ef4565]/30 bg-[#fff0f4] px-4 py-3 text-sm font-semibold text-[#042240]"
                    >
                        {{ reportStatus }}
                    </p>
                    <p
                        v-if="correctionStatus"
                        class="mb-4 rounded-sm border border-[#ef4565]/30 bg-[#fff0f4] px-4 py-3 text-sm font-semibold text-[#042240]"
                    >
                        {{ correctionStatus }}
                    </p>

                    <form
                        v-if="activeComposer === 'discussion'"
                        class="mb-6 grid gap-3 rounded-sm border border-[#cbd8e2]/35 bg-[#ffffff] p-4"
                        @submit.prevent="submitDiscussion"
                    >
                        <label
                            class="text-sm font-semibold"
                            for="photo-comment-content"
                        >
                            发表评论
                        </label>
                        <textarea
                            id="photo-comment-content"
                            v-model="commentContent"
                            rows="4"
                            maxlength="1000"
                            class="w-full rounded-sm border border-[#cbd8e2]/50 bg-[#ffffff] px-3 py-2 text-sm text-[#042240] transition outline-none focus:border-[#042240] focus:ring-2 focus:ring-[#042240]/20"
                            placeholder="写下与这张图片相关的讨论、背景或记忆"
                        ></textarea>
                        <CommentEmojiPicker
                            v-model="commentContent"
                            target-id="photo-comment-content"
                        />
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="h-10 rounded-sm border border-[#cbd8e2]/60 px-3 text-sm font-semibold"
                                @click="activeComposer = null"
                            >
                                取消
                            </button>
                            <button
                                type="submit"
                                class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#042240] px-4 text-sm font-semibold text-[#ffffff] transition hover:bg-[#238fdf] disabled:opacity-70"
                                :disabled="commentPending"
                            >
                                <Send class="h-4 w-4" aria-hidden="true" />
                                {{ commentPending ? '提交中' : '提交评论' }}
                            </button>
                        </div>
                    </form>

                    <form
                        v-if="activeComposer === 'correction'"
                        class="mb-6 grid gap-4 rounded-sm border border-[#cbd8e2]/35 bg-[#ffffff] p-4"
                        @submit.prevent="submitCorrection"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold">
                                建议修正字段
                                <select
                                    v-model="correctionField"
                                    class="h-10 rounded-sm border border-[#cbd8e2]/50 bg-[#ffffff] px-3 text-sm text-[#042240] transition outline-none focus:border-[#042240] focus:ring-2 focus:ring-[#042240]/20"
                                >
                                    <option
                                        v-for="field in correctionFields"
                                        :key="field.value"
                                        :value="field.value"
                                    >
                                        {{ field.label }}
                                    </option>
                                </select>
                            </label>
                            <label class="grid gap-2 text-sm font-semibold">
                                建议内容
                                <input
                                    v-model="correctionSuggestedValue"
                                    class="h-10 rounded-sm border border-[#cbd8e2]/50 bg-[#ffffff] px-3 text-sm text-[#042240] transition outline-none focus:border-[#042240] focus:ring-2 focus:ring-[#042240]/20"
                                    maxlength="1000"
                                    placeholder="例如：2022 世界杯决赛"
                                />
                            </label>
                        </div>
                        <label class="grid gap-2 text-sm font-semibold">
                            证据链接
                            <input
                                v-model="correctionEvidenceUrl"
                                class="h-10 rounded-sm border border-[#cbd8e2]/50 bg-[#ffffff] px-3 text-sm text-[#042240] transition outline-none focus:border-[#042240] focus:ring-2 focus:ring-[#042240]/20"
                                placeholder="可选，粘贴来源链接"
                            />
                        </label>
                        <label
                            class="grid gap-2 text-sm font-semibold"
                            for="photo-correction-content"
                        >
                            补充说明
                            <textarea
                                id="photo-correction-content"
                                v-model="correctionContent"
                                rows="4"
                                maxlength="1000"
                                class="w-full rounded-sm border border-[#cbd8e2]/50 bg-[#ffffff] px-3 py-2 text-sm text-[#042240] transition outline-none focus:border-[#042240] focus:ring-2 focus:ring-[#042240]/20"
                                placeholder="说明为什么需要补充或修正"
                            ></textarea>
                        </label>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="h-10 rounded-sm border border-[#cbd8e2]/60 px-3 text-sm font-semibold"
                                @click="activeComposer = null"
                            >
                                取消
                            </button>
                            <button
                                type="submit"
                                class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#ba9f6c] px-4 text-sm font-semibold text-[#042240] transition hover:bg-[#ba9f6c]/90 disabled:opacity-70"
                                :disabled="correctionPending"
                            >
                                <Send class="h-4 w-4" aria-hidden="true" />
                                {{
                                    correctionPending
                                        ? '提交中'
                                        : '提交补充 / 纠错'
                                }}
                            </button>
                        </div>
                    </form>

                    <div
                        v-for="item in pendingDiscussions"
                        :key="item.id"
                        class="comment-pending"
                    >
                        <span>我的内容 · 待审核（仅自己可见）</span>
                        <p><CommentContent :text="item.content" /></p>
                    </div>
                    <CommentThreadList
                        :key="photoDetail.photo.uuid"
                        :initial="photoDetail.comments.data"
                        :uuid="photoDetail.photo.uuid"
                        :can-interact="photoDetail.comments.can_submit"
                        :has-more="photoDetail.comments.has_more"
                        :blocked-reason="photoDetail.comments.blocked_reason"
                        @report="openReport"
                    />
                    <p
                        v-if="!photoDetail.comments.data.length"
                        class="detail-notice"
                    >
                        还没有公开评论，欢迎分享你的发现。
                    </p>
                    <form
                        v-if="activeReportId !== null"
                        id="comment-report-form"
                        class="mt-4 grid gap-3 rounded-sm border border-[#ef4565]/25 bg-[#fff0f4] p-4"
                        @submit.prevent="
                            activeReportId !== null &&
                            submitReport(activeReportId)
                        "
                    >
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold">
                                举报原因
                                <select
                                    v-model="reportReason"
                                    class="h-10 rounded-sm border border-[#cbd8e2]/50 bg-[#ffffff] px-3 text-sm text-[#042240] transition outline-none focus:border-[#ef4565] focus:ring-2 focus:ring-[#ef4565]/20"
                                >
                                    <option
                                        v-for="reason in reportReasons"
                                        :key="reason.value"
                                        :value="reason.value"
                                    >
                                        {{ reason.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <label class="grid gap-2 text-sm font-semibold">
                            补充说明
                            <textarea
                                v-model="reportDetails"
                                rows="3"
                                maxlength="1000"
                                class="w-full rounded-sm border border-[#cbd8e2]/50 bg-[#ffffff] px-3 py-2 text-sm text-[#042240] transition outline-none focus:border-[#ef4565] focus:ring-2 focus:ring-[#ef4565]/20"
                                placeholder="说明这条评论的问题，可为空"
                            ></textarea>
                        </label>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="h-10 rounded-sm border border-[#cbd8e2]/60 px-3 text-sm font-semibold"
                                @click="activeReportId = null"
                            >
                                取消
                            </button>
                            <button
                                type="submit"
                                class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#ba9f6c] px-4 text-sm font-semibold text-[#042240] transition hover:bg-[#ba9f6c]/90 disabled:opacity-70"
                                :disabled="reportPending"
                            >
                                <Send class="h-4 w-4" aria-hidden="true" />
                                {{ reportPending ? '提交中' : '提交举报' }}
                            </button>
                        </div>
                    </form>
                </section>

                <section
                    class="detail-section detail-similar"
                    aria-labelledby="similar-photos-heading"
                >
                    <h2 id="similar-photos-heading">相似图片</h2>
                    <p>按画面相似程度推荐，可帮助查找同一瞬间的其他版本。</p>
                    <SimilarPhotoGrid
                        v-if="photoDetail.similar.data.length"
                        :photos="photoDetail.similar.data"
                    />
                    <template v-else>
                        <div
                            class="detail-similar-placeholders"
                            aria-hidden="true"
                        >
                            <div
                                v-for="index in 4"
                                :key="index"
                                class="detail-similar-placeholder"
                            >
                                <div
                                    class="detail-similar-placeholder-image"
                                ></div>
                                <div
                                    class="detail-similar-placeholder-caption"
                                ></div>
                            </div>
                        </div>
                        <p class="detail-similar-placeholder-note">
                            暂无相似图片，灰色卡片为布局占位。
                        </p>
                    </template>
                </section>
            </div>
            <section
                v-if="hasRelated"
                class="detail-section"
                aria-labelledby="related-photos-heading"
            >
                <div class="detail-section-heading">
                    <h2 id="related-photos-heading">相关推荐</h2>
                    <span>{{ photoDetail.context.title }}</span>
                </div>
                <div class="detail-related">
                    <Link
                        v-for="photo in photoDetail.related.data"
                        :key="photo.uuid"
                        :href="photo.url"
                        :aria-label="`查看图片：${photo.title}`"
                        ><div class="detail-thumbnail">
                            <img
                                v-if="
                                    photo.image_url &&
                                    !relatedImageFailures[photo.id]
                                "
                                :src="photo.image_url"
                                :alt="photo.alt"
                                loading="lazy"
                                @error="relatedImageFailures[photo.id] = true"
                            />
                            <div
                                v-else
                                class="detail-image-error"
                                role="img"
                                :aria-label="photo.title + '，预览暂不可用'"
                            >
                                <ImageOff aria-hidden="true" />
                            </div>
                            <span class="detail-entry-arrow" aria-hidden="true"
                                >↗</span
                            >
                        </div>
                        <h3>{{ photo.title }}</h3>
                        <p>{{ formatDate(photo.event_date) }}</p></Link
                    >
                </div>
            </section>
            <div v-else class="detail-section detail-notice">
                <FileText aria-hidden="true" />
                <p>暂无相关推荐</p>
            </div>
        </section>
        <dialog
            v-if="!embedded"
            ref="zoomDialog"
            class="detail-zoom-dialog"
            aria-label="放大查看图片"
            @keydown.left.prevent="stepZoom('previous')"
            @keydown.right.prevent="stepZoom('next')"
            @click="
                $event.target === $event.currentTarget && zoomDialog?.close()
            "
        >
            <button
                type="button"
                class="detail-zoom-close"
                aria-label="关闭放大查看"
                @click="zoomDialog?.close()"
            >
                <X :size="24" aria-hidden="true" />
            </button>
            <img
                v-if="photoDetail.photo.image_url"
                :src="photoDetail.photo.image_url"
                :alt="photoDetail.photo.alt"
            />
            <button
                v-if="photoDetail.adjacent.previous"
                type="button"
                class="album-slide-arrow album-slide-previous"
                aria-label="放大视图上一张"
                :disabled="zoomPending"
                @click="stepZoom('previous')"
            >
                <ChevronLeft aria-hidden="true" />
            </button>
            <button
                v-if="photoDetail.adjacent.next"
                type="button"
                class="album-slide-arrow album-slide-next"
                aria-label="放大视图下一张"
                :disabled="zoomPending"
                @click="stepZoom('next')"
            >
                <ChevronRight aria-hidden="true" />
            </button>
            <p aria-live="polite">{{ photoDetail.photo.title }}</p>
        </dialog>
    </component>
</template>
