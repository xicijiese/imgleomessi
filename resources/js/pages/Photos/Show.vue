<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    Copy,
    ExternalLink,
    FileText,
    Flag,
    Heart,
    ImageOff,
    Images,
    Info,
    MessageCircle,
    PencilLine,
    QrCode,
    Send,
    Share2,
    Tag,
    ThumbsUp,
    X,
} from 'lucide-vue-next';
import { toDataURL } from 'qrcode';
import { computed, ref, watch } from 'vue';

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

interface CommentSummary {
    id: number;
    user_name: string;
    content: string;
    created_at: string | null;
}

interface CommentsPayload {
    can_submit: boolean;
    can_report: boolean;
    is_blocked: boolean;
    blocked_reason: string | null;
    total: number;
    data: CommentSummary[];
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
    comments: CommentsPayload;
}

const props = defineProps<{
    photoDetail: PhotoDetailPayload;
}>();

type ComposerMode = 'discussion' | 'correction' | null;
type ReportReason = 'spam' | 'abuse' | 'copyright' | 'misleading' | 'other';
type ShareChannel = 'native_share' | 'copy_link' | 'weibo' | 'wechat_qr';

const imageFailed = ref(false);
const relatedImageFailures = ref<Record<number, boolean>>({});
const favoritePending = ref(false);
const likePending = ref(false);
const sharePanelOpen = ref(false);
const activeShareChannel = ref<ShareChannel | null>(null);
const shareStatus = ref<string | null>(null);
const wechatQrDataUrl = ref<string | null>(null);
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

const pageUrl = computed(() =>
    typeof window === 'undefined'
        ? props.photoDetail.photo.url
        : window.location.href,
);
const favoriteUrl = computed(
    () => '/photos/' + props.photoDetail.photo.uuid + '/favorite',
);
const likeUrl = computed(
    () => '/photos/' + props.photoDetail.photo.uuid + '/like',
);
const shareUrl = computed(
    () => '/photos/' + props.photoDetail.photo.uuid + '/shares',
);
const weiboShareUrl = computed(() => {
    const params = new URLSearchParams({
        title: props.photoDetail.photo.title,
        url: pageUrl.value,
    });

    return 'https://service.weibo.com/share/share.php?' + params.toString();
});
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
const hasTaxonomy = computed(
    () =>
        props.photoDetail.photo.categories.length > 0 ||
        props.photoDetail.photo.tags.length > 0,
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

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const interactionRequest = async <T,>(
    url: string,
    method: 'POST' | 'DELETE',
    body?: Record<string, string>,
): Promise<T> => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

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

const toggleFavorite = async () => {
    if (favoritePending.value) {
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

    favoritePending.value = true;
    const wasFavorited = interactions.value.is_favorited;
    interactions.value.is_favorited = !wasFavorited;

    try {
        const result = await interactionRequest<{ is_favorited: boolean }>(
            favoriteUrl.value,
            wasFavorited ? 'DELETE' : 'POST',
        );

        interactions.value.is_favorited = result.is_favorited;
    } catch {
        interactions.value.is_favorited = wasFavorited;
    } finally {
        favoritePending.value = false;
    }
};

const toggleLike = async () => {
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
    } catch {
        interactions.value.is_liked = wasLiked;
        interactions.value.likes_count = previousLikesCount;
    } finally {
        likePending.value = false;
    }
};

const canShare = () => {
    if (!interactions.value.blocked_reason) {
        return true;
    }

    shareStatus.value = interactions.value.blocked_reason;
    clearShareStatus();

    return false;
};

const setShareStatus = (message: string) => {
    shareStatus.value = message;
    clearShareStatus();
};

const writeShareLink = async () => {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(pageUrl.value);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = pageUrl.value;
    textarea.setAttribute('readonly', 'true');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    document.body.removeChild(textarea);

    if (!copied) {
        throw new Error('copy_failed');
    }
};

const recordShare = async (channel: ShareChannel, message: string) => {
    activeShareChannel.value = channel;

    try {
        await interactionRequest<{ recorded: boolean }>(
            shareUrl.value,
            'POST',
            {
                channel,
                page_url: pageUrl.value,
            },
        );
        setShareStatus(message);
        return true;
    } catch {
        setShareStatus('分享已完成，但记录失败');
        return false;
    } finally {
        activeShareChannel.value = null;
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
};

const submitDiscussion = async () => {
    if (commentPending.value) {
        return;
    }

    const content = commentContent.value.trim();

    if (content.length < 2) {
        commentStatus.value = '评论至少需要 2 个字。';
        clearCommentStatus();
        return;
    }

    commentPending.value = true;

    try {
        const result = await interactionRequest<{ message: string }>(
            commentUrl.value,
            'POST',
            { content },
        );
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
const toggleSharePanel = () => {
    if (!canShare()) {
        return;
    }

    sharePanelOpen.value = !sharePanelOpen.value;
};

const copyShareLink = async (channel: ShareChannel = 'copy_link') => {
    if (!canShare() || activeShareChannel.value) {
        return;
    }

    try {
        await writeShareLink();
        await recordShare(channel, '链接已复制');
    } catch {
        setShareStatus('复制失败，请手动复制链接');
    }
};

const shareWithNative = async () => {
    if (!canShare() || activeShareChannel.value) {
        return;
    }

    if (!navigator.share) {
        await copyShareLink('copy_link');
        return;
    }

    activeShareChannel.value = 'native_share';

    try {
        await navigator.share({
            title: props.photoDetail.photo.title,
            text: props.photoDetail.photo.description ?? undefined,
            url: pageUrl.value,
        });
        await recordShare('native_share', '已调用系统分享');
    } catch (error) {
        setShareStatus(
            error instanceof DOMException && error.name === 'AbortError'
                ? '已取消分享'
                : '系统分享失败，可复制链接',
        );
    } finally {
        activeShareChannel.value = null;
    }
};

const shareToWeibo = async () => {
    if (!canShare() || activeShareChannel.value) {
        return;
    }

    const opened = window.open(
        weiboShareUrl.value,
        '_blank',
        'noopener,noreferrer,width=720,height=560',
    );

    await recordShare(
        'weibo',
        opened ? '已打开微博分享' : '微博窗口被拦截，可复制链接',
    );
};

const showWechatQr = async () => {
    if (!canShare() || activeShareChannel.value) {
        return;
    }

    activeShareChannel.value = 'wechat_qr';

    try {
        wechatQrDataUrl.value = await toDataURL(pageUrl.value, {
            width: 192,
            margin: 1,
            color: {
                dark: '#094067',
                light: '#fffffe',
            },
        });
        await recordShare('wechat_qr', '微信扫码后可打开链接');
    } catch {
        setShareStatus('二维码生成失败，可复制链接');
    } finally {
        activeShareChannel.value = null;
    }
};
</script>

<template>
    <SeoHead :seo="photoDetail.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="photoDetail.navigation"
            :site="photoDetail.site"
        />

        <section class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div
                    class="mb-5 flex flex-wrap items-center justify-between gap-3"
                >
                    <Link
                        :href="photoDetail.context.return_url"
                        class="inline-flex h-11 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                        {{ contextLabel }}
                    </Link>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-11 min-w-24 items-center justify-center gap-2 rounded-sm border px-4 text-sm font-semibold transition"
                            :class="
                                interactions.is_favorited
                                    ? 'border-[#ef4565] bg-[#ef4565] text-[#fffffe] hover:bg-[#d9375d]'
                                    : 'border-[#90b4ce]/60 text-[#094067] hover:border-[#ef4565] hover:text-[#ef4565]'
                            "
                            :aria-pressed="interactions.is_favorited"
                            :aria-busy="favoritePending"
                            @click="toggleFavorite"
                        >
                            <Heart
                                class="h-4 w-4"
                                :class="
                                    interactions.is_favorited
                                        ? 'fill-current'
                                        : ''
                                "
                                aria-hidden="true"
                            />
                            {{ interactions.is_favorited ? '已收藏' : '收藏' }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-11 min-w-28 items-center justify-center gap-2 rounded-sm border px-4 text-sm font-semibold transition"
                            :class="
                                interactions.is_liked
                                    ? 'border-[#3da9fc] bg-[#3da9fc] text-[#fffffe] hover:bg-[#238fdf]'
                                    : 'border-[#90b4ce]/60 text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]'
                            "
                            :aria-pressed="interactions.is_liked"
                            :aria-busy="likePending"
                            @click="toggleLike"
                        >
                            <ThumbsUp
                                class="h-4 w-4"
                                :class="
                                    interactions.is_liked ? 'fill-current' : ''
                                "
                                aria-hidden="true"
                            />
                            <span>{{ interactions.likes_count }}</span>
                        </button>

                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex h-11 min-w-24 items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold transition hover:border-[#3da9fc] hover:text-[#3da9fc]"
                                aria-controls="photo-share-panel"
                                :aria-expanded="sharePanelOpen"
                                @click="toggleSharePanel"
                            >
                                <Share2 class="h-4 w-4" aria-hidden="true" />
                                分享
                            </button>

                            <div
                                v-if="sharePanelOpen"
                                id="photo-share-panel"
                                class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 text-[#094067] shadow-xl"
                            >
                                <div
                                    class="mb-3 flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <h2 class="text-sm font-semibold">
                                            分享这张图片
                                        </h2>
                                        <p class="mt-1 text-xs text-[#5f6c7b]">
                                            微信先用二维码 /
                                            链接引导，不接第三方 API。
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-sm border border-[#90b4ce]/40 text-[#5f6c7b] transition hover:border-[#ef4565] hover:text-[#ef4565]"
                                        aria-label="关闭分享面板"
                                        @click="sharePanelOpen = false"
                                    >
                                        <X class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="flex min-h-20 flex-col items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/40 px-3 text-sm font-semibold transition hover:border-[#3da9fc] hover:text-[#3da9fc]"
                                        @click="copyShareLink()"
                                    >
                                        <Copy
                                            class="h-5 w-5"
                                            aria-hidden="true"
                                        />
                                        复制链接
                                    </button>
                                    <button
                                        type="button"
                                        class="flex min-h-20 flex-col items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/40 px-3 text-sm font-semibold transition hover:border-[#3da9fc] hover:text-[#3da9fc]"
                                        @click="shareWithNative"
                                    >
                                        <Share2
                                            class="h-5 w-5"
                                            aria-hidden="true"
                                        />
                                        系统分享
                                    </button>
                                    <button
                                        type="button"
                                        class="flex min-h-20 flex-col items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/40 px-3 text-sm font-semibold transition hover:border-[#ef4565] hover:text-[#ef4565]"
                                        @click="shareToWeibo"
                                    >
                                        <ExternalLink
                                            class="h-5 w-5"
                                            aria-hidden="true"
                                        />
                                        微博分享
                                    </button>
                                    <button
                                        type="button"
                                        class="flex min-h-20 flex-col items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/40 px-3 text-sm font-semibold transition hover:border-[#094067] hover:text-[#094067]"
                                        @click="showWechatQr"
                                    >
                                        <QrCode
                                            class="h-5 w-5"
                                            aria-hidden="true"
                                        />
                                        微信扫码
                                    </button>
                                </div>

                                <div
                                    v-if="wechatQrDataUrl"
                                    class="mt-4 grid justify-items-center gap-2 rounded-sm bg-[#d8eefe]/55 p-4 text-center"
                                >
                                    <img
                                        :src="wechatQrDataUrl"
                                        :alt="`微信扫码打开：${photoDetail.photo.title}`"
                                        class="h-48 w-48 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-2"
                                    />
                                    <p class="text-xs text-[#5f6c7b]">
                                        用微信扫一扫打开当前图片链接
                                    </p>
                                </div>

                                <div
                                    class="mt-3 rounded-sm bg-[#fffffe] text-xs"
                                >
                                    <p class="break-all text-[#5f6c7b]">
                                        {{ pageUrl }}
                                    </p>
                                    <p
                                        v-if="shareStatus"
                                        class="mt-2 inline-flex items-center gap-1 font-semibold text-[#094067]"
                                    >
                                        <Check
                                            class="h-3.5 w-3.5 text-[#3da9fc]"
                                            aria-hidden="true"
                                        />
                                        {{ shareStatus }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_400px]"
                >
                    <figure class="min-w-0">
                        <div
                            class="flex max-h-[76vh] min-h-[320px] items-center justify-center overflow-hidden rounded-sm bg-[#d8eefe]"
                        >
                            <img
                                v-if="
                                    photoDetail.photo.image_url && !imageFailed
                                "
                                :src="photoDetail.photo.image_url"
                                :alt="photoDetail.photo.alt"
                                class="max-h-[76vh] w-full object-contain"
                                @error="imageFailed = true"
                            />
                            <div
                                v-else
                                class="flex min-h-[360px] w-full items-center justify-center bg-[linear-gradient(135deg,#d8eefe,#90b4ce)] text-[#094067]"
                            >
                                <ImageOff
                                    class="h-12 w-12"
                                    aria-hidden="true"
                                />
                            </div>
                        </div>
                    </figure>

                    <aside class="grid content-start gap-6">
                        <section class="border-b border-[#90b4ce]/30 pb-6">
                            <p
                                class="mb-3 text-sm font-semibold text-[#3da9fc]"
                            >
                                Photo Archive
                            </p>
                            <h1
                                class="text-3xl leading-tight font-semibold sm:text-4xl lg:text-3xl xl:text-4xl"
                            >
                                {{ photoDetail.photo.title }}
                            </h1>
                            <p
                                v-if="photoDetail.photo.description"
                                class="mt-4 text-sm leading-7 text-[#5f6c7b]"
                            >
                                {{ photoDetail.photo.description }}
                            </p>
                        </section>

                        <section
                            class="grid gap-4 border-b border-[#90b4ce]/30 pb-6"
                            aria-labelledby="photo-dates-heading"
                        >
                            <h2
                                id="photo-dates-heading"
                                class="text-base font-semibold"
                            >
                                日期
                            </h2>
                            <div class="grid gap-3 text-sm text-[#5f6c7b]">
                                <div class="flex items-center gap-2">
                                    <CalendarDays
                                        class="h-4 w-4 text-[#3da9fc]"
                                        aria-hidden="true"
                                    />
                                    <span
                                        >事件日期：{{
                                            formatDate(
                                                photoDetail.photo.event_date,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <CalendarDays
                                        class="h-4 w-4 text-[#3da9fc]"
                                        aria-hidden="true"
                                    />
                                    <span
                                        >拍摄时间：{{
                                            formatDate(
                                                photoDetail.photo.taken_at,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <CalendarDays
                                        class="h-4 w-4 text-[#3da9fc]"
                                        aria-hidden="true"
                                    />
                                    <span
                                        >发布时间：{{
                                            formatDate(
                                                photoDetail.photo.published_at,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="hasTaxonomy"
                            class="grid gap-4 border-b border-[#90b4ce]/30 pb-6"
                            aria-labelledby="photo-taxonomy-heading"
                        >
                            <h2
                                id="photo-taxonomy-heading"
                                class="text-base font-semibold"
                            >
                                分类与标签
                            </h2>
                            <div
                                v-if="photoDetail.photo.categories.length"
                                class="flex flex-wrap gap-2"
                            >
                                <Link
                                    v-for="category in photoDetail.photo
                                        .categories"
                                    :key="category.id"
                                    :href="category.url"
                                    class="inline-flex min-h-9 items-center rounded-full bg-[#d8eefe] px-3 text-xs font-semibold text-[#094067] hover:bg-[#3da9fc] hover:text-[#fffffe]"
                                >
                                    {{ category.root_name }} /
                                    {{ category.name }}
                                </Link>
                            </div>
                            <div
                                v-if="photoDetail.photo.tags.length"
                                class="flex flex-wrap gap-2"
                            >
                                <Link
                                    v-for="tag in photoDetail.photo.tags"
                                    :key="tag.id"
                                    :href="tag.url"
                                    class="inline-flex min-h-9 items-center gap-1 rounded-full border border-[#90b4ce]/60 px-3 text-xs font-semibold text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                                >
                                    <Tag
                                        class="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ tag.name }}
                                </Link>
                            </div>
                        </section>

                        <section
                            class="grid gap-4 border-b border-[#90b4ce]/30 pb-6"
                            aria-labelledby="photo-source-heading"
                        >
                            <h2
                                id="photo-source-heading"
                                class="text-base font-semibold"
                            >
                                来源与版权
                            </h2>
                            <div class="grid gap-3 text-sm text-[#5f6c7b]">
                                <div class="flex items-start gap-2">
                                    <Info
                                        class="mt-0.5 h-4 w-4 text-[#3da9fc]"
                                        aria-hidden="true"
                                    />
                                    <span
                                        >版权状态：{{
                                            photoDetail.photo.copyright_status
                                                .label
                                        }}</span
                                    >
                                </div>
                                <template v-if="photoDetail.photo.source">
                                    <a
                                        :href="photoDetail.photo.source.source_url"
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        class="inline-flex items-center gap-2 break-all text-[#094067] hover:text-[#3da9fc]"
                                    >
                                        <ExternalLink
                                            class="h-4 w-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        来源链接
                                    </a>
                                </template>
                                <p v-else>来源待补充</p>
                            </div>
                        </section>

                        <section
                            v-if="hasMeta"
                            class="grid gap-4 border-b border-[#90b4ce]/30 pb-6"
                            aria-labelledby="photo-file-heading"
                        >
                            <h2
                                id="photo-file-heading"
                                class="text-base font-semibold"
                            >
                                图片信息
                            </h2>
                            <div
                                class="grid grid-cols-2 gap-3 text-sm text-[#5f6c7b]"
                            >
                                <span
                                    v-if="
                                        photoDetail.photo.width &&
                                        photoDetail.photo.height
                                    "
                                    >尺寸：{{ photoDetail.photo.width }} x
                                    {{ photoDetail.photo.height }}</span
                                >
                                <span v-if="photoDetail.photo.mime_type"
                                    >格式：{{
                                        photoDetail.photo.mime_type
                                    }}</span
                                >
                                <span v-if="photoDetail.photo.file_size_label"
                                    >文件大小：{{
                                        photoDetail.photo.file_size_label
                                    }}</span
                                >
                            </div>
                        </section>

                        <section
                            v-if="photoDetail.photo.albums.length"
                            class="grid gap-4"
                            aria-labelledby="photo-albums-heading"
                        >
                            <h2
                                id="photo-albums-heading"
                                class="text-base font-semibold"
                            >
                                所属相册
                            </h2>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="album in photoDetail.photo.albums"
                                    :key="album.id"
                                    :href="album.url"
                                    class="inline-flex min-h-10 items-center gap-2 rounded-sm bg-[#d8eefe] px-3 text-sm font-semibold hover:bg-[#3da9fc] hover:text-[#fffffe]"
                                >
                                    <Images
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    {{ album.title }}
                                </Link>
                            </div>
                        </section>
                    </aside>
                </div>

                <nav
                    v-if="
                        photoDetail.adjacent.previous ||
                        photoDetail.adjacent.next
                    "
                    class="mt-8 grid gap-3 sm:grid-cols-2"
                    aria-label="上下张图片"
                >
                    <Link
                        v-if="photoDetail.adjacent.previous"
                        :href="photoDetail.adjacent.previous.url"
                        class="flex min-h-20 items-center gap-3 rounded-sm border border-[#90b4ce]/35 px-4 py-3 hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        <ChevronLeft
                            class="h-5 w-5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            <span class="block text-xs text-[#5f6c7b]"
                                >上一张</span
                            >
                            <span class="line-clamp-2 text-sm font-semibold">{{
                                photoDetail.adjacent.previous.title
                            }}</span>
                        </span>
                    </Link>
                    <span v-else class="hidden sm:block"></span>
                    <Link
                        v-if="photoDetail.adjacent.next"
                        :href="photoDetail.adjacent.next.url"
                        class="flex min-h-20 items-center justify-end gap-3 rounded-sm border border-[#90b4ce]/35 px-4 py-3 text-right hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        <span class="min-w-0">
                            <span class="block text-xs text-[#5f6c7b]"
                                >下一张</span
                            >
                            <span class="line-clamp-2 text-sm font-semibold">{{
                                photoDetail.adjacent.next.title
                            }}</span>
                        </span>
                        <ChevronRight
                            class="h-5 w-5 shrink-0"
                            aria-hidden="true"
                        />
                    </Link>
                </nav>

                <section
                    class="mt-12 border-t border-[#90b4ce]/30 pt-10"
                    aria-labelledby="photo-comments-heading"
                >
                    <div
                        class="mb-5 flex flex-wrap items-end justify-between gap-4"
                    >
                        <div>
                            <p
                                class="mb-2 text-sm font-semibold text-[#3da9fc]"
                            >
                                Discussion
                            </p>
                            <h2
                                id="photo-comments-heading"
                                class="text-2xl font-semibold"
                            >
                                评论与补充
                            </h2>
                            <p class="mt-2 text-sm text-[#5f6c7b]">
                                已公开 {{ photoDetail.comments.total }} 条评论
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold transition hover:border-[#3da9fc] hover:text-[#3da9fc]"
                                :class="
                                    activeComposer === 'discussion'
                                        ? 'border-[#3da9fc] bg-[#3da9fc] text-[#fffffe]'
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
                                class="inline-flex h-10 items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold transition hover:border-[#ef4565] hover:text-[#ef4565]"
                                :class="
                                    activeComposer === 'correction'
                                        ? 'border-[#ef4565] bg-[#ef4565] text-[#fffffe]'
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
                        class="mb-4 rounded-sm bg-[#d8eefe]/70 px-4 py-3 text-sm text-[#5f6c7b]"
                    >
                        登录后可以发表评论或提交图片信息补充 / 纠错。
                    </p>

                    <p
                        v-if="commentStatus"
                        class="mb-4 rounded-sm border border-[#3da9fc]/30 bg-[#d8eefe]/55 px-4 py-3 text-sm font-semibold text-[#094067]"
                    >
                        {{ commentStatus }}
                    </p>
                    <p
                        v-if="reportStatus"
                        class="mb-4 rounded-sm border border-[#ef4565]/30 bg-[#fff0f4] px-4 py-3 text-sm font-semibold text-[#094067]"
                    >
                        {{ reportStatus }}
                    </p>
                    <p
                        v-if="correctionStatus"
                        class="mb-4 rounded-sm border border-[#ef4565]/30 bg-[#fff0f4] px-4 py-3 text-sm font-semibold text-[#094067]"
                    >
                        {{ correctionStatus }}
                    </p>

                    <form
                        v-if="activeComposer === 'discussion'"
                        class="mb-6 grid gap-3 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4"
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
                            class="w-full rounded-sm border border-[#90b4ce]/50 bg-[#fffffe] px-3 py-2 text-sm text-[#094067] transition outline-none focus:border-[#3da9fc] focus:ring-2 focus:ring-[#3da9fc]/20"
                            placeholder="写下与这张图片相关的讨论、背景或记忆"
                        ></textarea>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="h-10 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold"
                                @click="activeComposer = null"
                            >
                                取消
                            </button>
                            <button
                                type="submit"
                                class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#3da9fc] px-4 text-sm font-semibold text-[#fffffe] transition hover:bg-[#238fdf] disabled:opacity-70"
                                :disabled="commentPending"
                            >
                                <Send class="h-4 w-4" aria-hidden="true" />
                                {{ commentPending ? '提交中' : '提交评论' }}
                            </button>
                        </div>
                    </form>

                    <form
                        v-if="activeComposer === 'correction'"
                        class="mb-6 grid gap-4 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4"
                        @submit.prevent="submitCorrection"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold">
                                建议修正字段
                                <select
                                    v-model="correctionField"
                                    class="h-10 rounded-sm border border-[#90b4ce]/50 bg-[#fffffe] px-3 text-sm text-[#094067] transition outline-none focus:border-[#3da9fc] focus:ring-2 focus:ring-[#3da9fc]/20"
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
                                    class="h-10 rounded-sm border border-[#90b4ce]/50 bg-[#fffffe] px-3 text-sm text-[#094067] transition outline-none focus:border-[#3da9fc] focus:ring-2 focus:ring-[#3da9fc]/20"
                                    maxlength="1000"
                                    placeholder="例如：2022 世界杯决赛"
                                />
                            </label>
                        </div>
                        <label class="grid gap-2 text-sm font-semibold">
                            证据链接
                            <input
                                v-model="correctionEvidenceUrl"
                                class="h-10 rounded-sm border border-[#90b4ce]/50 bg-[#fffffe] px-3 text-sm text-[#094067] transition outline-none focus:border-[#3da9fc] focus:ring-2 focus:ring-[#3da9fc]/20"
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
                                class="w-full rounded-sm border border-[#90b4ce]/50 bg-[#fffffe] px-3 py-2 text-sm text-[#094067] transition outline-none focus:border-[#3da9fc] focus:ring-2 focus:ring-[#3da9fc]/20"
                                placeholder="说明为什么需要补充或修正"
                            ></textarea>
                        </label>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="h-10 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold"
                                @click="activeComposer = null"
                            >
                                取消
                            </button>
                            <button
                                type="submit"
                                class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#ef4565] px-4 text-sm font-semibold text-[#fffffe] transition hover:bg-[#d9375d] disabled:opacity-70"
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
                        v-if="photoDetail.comments.data.length"
                        class="grid gap-3"
                    >
                        <article
                            v-for="comment in photoDetail.comments.data"
                            :key="comment.id"
                            class="rounded-sm border border-[#90b4ce]/25 bg-[#fffffe] p-4"
                        >
                            <div
                                class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm"
                            >
                                <strong>{{ comment.user_name }}</strong>
                                <span
                                    class="inline-flex items-center gap-3 text-xs text-[#5f6c7b]"
                                >
                                    <time>{{ comment.created_at }}</time>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold text-[#5f6c7b] transition hover:text-[#ef4565]"
                                        @click="openReport(comment.id)"
                                    >
                                        <Flag
                                            class="h-3.5 w-3.5"
                                            aria-hidden="true"
                                        />
                                        举报
                                    </button>
                                </span>
                            </div>
                            <p
                                class="text-sm leading-7 whitespace-pre-line text-[#5f6c7b]"
                            >
                                {{ comment.content }}
                            </p>
                            <form
                                v-if="activeReportId === comment.id"
                                class="mt-4 grid gap-3 rounded-sm border border-[#ef4565]/25 bg-[#fff0f4] p-4"
                                @submit.prevent="submitReport(comment.id)"
                            >
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label
                                        class="grid gap-2 text-sm font-semibold"
                                    >
                                        举报原因
                                        <select
                                            v-model="reportReason"
                                            class="h-10 rounded-sm border border-[#90b4ce]/50 bg-[#fffffe] px-3 text-sm text-[#094067] transition outline-none focus:border-[#ef4565] focus:ring-2 focus:ring-[#ef4565]/20"
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
                                        class="w-full rounded-sm border border-[#90b4ce]/50 bg-[#fffffe] px-3 py-2 text-sm text-[#094067] transition outline-none focus:border-[#ef4565] focus:ring-2 focus:ring-[#ef4565]/20"
                                        placeholder="说明这条评论的问题，可为空"
                                    ></textarea>
                                </label>
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="h-10 rounded-sm border border-[#90b4ce]/60 px-3 text-sm font-semibold"
                                        @click="activeReportId = null"
                                    >
                                        取消
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#ef4565] px-4 text-sm font-semibold text-[#fffffe] transition hover:bg-[#d9375d] disabled:opacity-70"
                                        :disabled="reportPending"
                                    >
                                        <Send
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        {{
                                            reportPending
                                                ? '提交中'
                                                : '提交举报'
                                        }}
                                    </button>
                                </div>
                            </form>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-sm border border-dashed border-[#90b4ce]/60 bg-[#d8eefe]/55 px-6 py-8 text-center"
                    >
                        <MessageCircle
                            class="mx-auto h-8 w-8 text-[#3da9fc]"
                            aria-hidden="true"
                        />
                        <p class="mt-3 text-sm text-[#5f6c7b]">
                            还没有公开评论
                        </p>
                    </div>
                </section>
                <section
                    v-if="hasRelated"
                    class="mt-12"
                    aria-labelledby="related-photos-heading"
                >
                    <div class="mb-5 flex items-end justify-between gap-4">
                        <div>
                            <h2
                                id="related-photos-heading"
                                class="text-2xl font-semibold"
                            >
                                相关推荐
                            </h2>
                            <p class="mt-2 text-sm text-[#5f6c7b]">
                                {{ photoDetail.context.title }}
                            </p>
                        </div>
                        <Share2
                            class="hidden h-5 w-5 text-[#3da9fc] sm:block"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            v-for="photo in photoDetail.related.data"
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
                                        !relatedImageFailures[photo.id]
                                    "
                                    :src="photo.image_url"
                                    :alt="photo.alt"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                                    @error="
                                        relatedImageFailures[photo.id] = true
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
                            <div class="grid min-h-28 gap-2 p-3">
                                <h3
                                    class="line-clamp-2 text-sm leading-6 font-semibold"
                                >
                                    {{ photo.title }}
                                </h3>
                                <p class="text-xs text-[#5f6c7b]">
                                    {{ formatDate(photo.event_date) }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </section>

                <div
                    v-if="!hasRelated"
                    class="mt-12 rounded-sm border border-dashed border-[#90b4ce]/60 bg-[#d8eefe]/55 px-6 py-10 text-center"
                >
                    <FileText
                        class="mx-auto h-9 w-9 text-[#3da9fc]"
                        aria-hidden="true"
                    />
                    <p class="mt-3 text-sm text-[#5f6c7b]">暂无相关推荐</p>
                </div>
            </div>
        </section>
    </main>
</template>
