<script setup lang="ts">
import BadgeIcon from '@/components/BadgeIcon.vue';
import CommentContent from '@/components/CommentContent.vue';
import CommentEmojiPicker from '@/components/CommentEmojiPicker.vue';
import { shareRequest } from '@/lib/sharing';
import type { ThreadComment } from '@/types/comments';
import { Link, router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { BadgeCheck, ShieldCheck, ThumbsUp, UserRound } from 'lucide-vue-next';
import { nextTick, onUnmounted, ref, watch } from 'vue';
const props = defineProps<{
    comment: ThreadComment;
    uuid: string;
    canInteract: boolean;
    isReply?: boolean;
    blockedReason?: string | null;
}>();
const emit = defineEmits<{
    report: [id: number];
    reply: [comment: ThreadComment];
}>();
const liked = ref(props.comment.liked),
    count = ref(props.comment.like_count),
    busy = ref(false),
    message = ref(''),
    avatarFailed = ref(false),
    allBadges = ref(false);
const replies = ref(props.comment.replies ?? []),
    repliesExpanded = ref(false),
    replyPage = ref(0),
    loading = ref(false),
    more = ref((props.comment.reply_count ?? 0) > replies.value.length);
const target = ref<ThreadComment | null>(null),
    content = ref(''),
    sending = ref(false);
watch(
    () => props.comment,
    (value) => {
        replies.value = value.replies ?? [];
        replyPage.value = 0;
        more.value = (value.reply_count ?? 0) > replies.value.length;
        liked.value = value.liked;
        count.value = value.like_count;
        if (repliesExpanded.value) void load();
    },
);
const replyForm = ref<HTMLElement | null>(null);
let replyMotion: gsap.core.Tween | undefined;
watch(target, async (value) => {
    replyMotion?.revert();
    await nextTick();
    if (value && replyForm.value) {
        replyForm.value
            .querySelector('textarea')
            ?.focus({ preventScroll: true });
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches)
            replyMotion = gsap.fromTo(
                replyForm.value,
                { opacity: 0, y: 4 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.16,
                    clearProps: 'opacity,transform',
                },
            );
    }
});
onUnmounted(() => replyMotion?.revert());
const requireLogin = () => {
    if (!props.canInteract) {
        if (props.blockedReason) {
            message.value = props.blockedReason;
            return false;
        }
        router.visit('/login');
        return false;
    }
    return true;
};
const reply = (c: ThreadComment) => {
    if (!requireLogin()) return;
    if (props.isReply) emit('reply', c);
    else target.value = c;
};
const like = async () => {
    if (busy.value || !requireLogin()) return;
    busy.value = true;
    try {
        const r = await shareRequest<{ liked: boolean; like_count: number }>(
            `/comments/${props.comment.id}/like`,
            {},
        );
        liked.value = r.liked;
        count.value = r.like_count;
    } catch {
        message.value = '点赞未保存，请重试。';
    } finally {
        busy.value = false;
    }
};
// DELETE 独立发送，避免分享请求帮助函数的GET默认行为。
const toggleLike = async () => {
    if (!liked.value) return like();
    if (busy.value || !requireLogin()) return;
    busy.value = true;
    try {
        const token = document.cookie
            .split('; ')
            .find((p) => p.startsWith('XSRF-TOKEN='))
            ?.slice(11);
        const r = await fetch(`/comments/${props.comment.id}/like`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
            },
        });
        if (!r.ok) throw new Error();
        const data = await r.json();
        liked.value = data.liked;
        count.value = data.like_count;
    } catch {
        message.value = '取消点赞失败，请重试。';
    } finally {
        busy.value = false;
    }
};
const load = async () => {
    if (loading.value) return;
    loading.value = true;
    try {
        const page = replyPage.value + 1;
        const r = await shareRequest<{
            data: ThreadComment[];
            has_more: boolean;
        }>(
            `/photos/${props.uuid}/discussion?parent=${props.comment.id}&reply_page=${page}`,
        );
        replies.value = page === 1 ? r.data : [...replies.value, ...r.data];
        replyPage.value = page;
        more.value = r.has_more;
    } catch {
        message.value = '回复加载失败，请重试。';
    } finally {
        loading.value = false;
    }
};
const toggleReplies = async () => {
    repliesExpanded.value = !repliesExpanded.value;
    if (repliesExpanded.value && replyPage.value === 0) await load();
};
const submit = async () => {
    if (!target.value || sending.value) return;
    sending.value = true;
    try {
        const r = await shareRequest<{ id: number; message: string }>(
            `/photos/${props.uuid}/comments`,
            { reply_to: target.value.id, content: content.value },
        );
        router.reload({
            only: ['photoDetail', 'albumDetail'],
        });
        content.value = '';
        target.value = null;
        message.value = r.message;
    } catch (error) {
        message.value =
            error instanceof Error
                ? error.message
                : '回复未提交，请检查字数或稍后重试。';
    } finally {
        sending.value = false;
    }
};
</script>
<template>
    <div class="comment-thread-row" :class="{ 'is-reply': isReply }">
        <component
            :is="comment.author.url ? Link : 'span'"
            :href="comment.author.url ?? undefined"
            class="comment-avatar"
            :aria-label="comment.author.name + '的主页'"
        >
            <img
                v-if="comment.author.avatar && !avatarFailed"
                :src="comment.author.avatar"
                alt=""
                loading="lazy"
                @error="avatarFailed = true"
            /><UserRound v-else :size="22" aria-hidden="true" />
        </component>
        <div class="comment-body">
            <div class="comment-author">
                <component
                    :is="comment.author.url ? Link : 'span'"
                    :href="comment.author.url ?? undefined"
                    class="comment-name"
                    >{{ comment.author.name }}</component
                >
                <button
                    v-for="identity in comment.author.identities"
                    :key="identity"
                    type="button"
                    class="comment-identity"
                    :class="{ 'is-guardian': identity === '网站守护者' }"
                    :aria-label="identity"
                    :title="identity"
                    @click="message = identity"
                >
                    <ShieldCheck
                        v-if="identity === '网站守护者'"
                        :size="18"
                    /><BadgeCheck v-else :size="18" />
                </button>
                <button
                    v-for="badge in allBadges
                        ? comment.author.badges
                        : comment.author.badges.slice(0, 3)"
                    :key="badge.id"
                    type="button"
                    class="comment-badge"
                    :title="badge.name"
                    :aria-label="badge.name"
                    @click="message = badge.name"
                >
                    <BadgeIcon :badge="badge" />
                </button>
                <button
                    v-if="comment.author.badges.length > 3"
                    type="button"
                    @click="allBadges = !allBadges"
                >
                    {{
                        allBadges
                            ? '收起'
                            : `+${comment.author.badges.length - 3}`
                    }}
                </button>
            </div>
            <p class="comment-text">
                <span v-if="comment.reply_to" class="comment-reply-to"
                    >回复 @{{ comment.reply_to }}：</span
                ><CommentContent :text="comment.content" />
            </p>
            <div class="comment-actions">
                <time>{{ comment.created_at }}</time
                ><button type="button" @click="reply(comment)">回复</button
                ><button
                    type="button"
                    :disabled="busy"
                    :aria-pressed="liked"
                    @click="toggleLike"
                >
                    <ThumbsUp :size="15" aria-hidden="true" />{{
                        count || '赞'
                    }}</button
                ><button
                    type="button"
                    class="comment-report"
                    @click="requireLogin() && emit('report', comment.id)"
                >
                    举报
                </button>
            </div>
            <p v-if="message" role="status" class="comment-feedback">
                {{ message }}
            </p>
            <button
                v-if="!isReply && (comment.reply_count ?? 0) > 0"
                type="button"
                class="comment-load-replies"
                :aria-expanded="repliesExpanded"
                :aria-controls="`replies-${comment.id}`"
                :disabled="loading"
                @click="toggleReplies"
            >
                {{
                    loading
                        ? '正在加载回复'
                        : repliesExpanded
                          ? '收起回复'
                          : `查看 ${comment.reply_count} 条回复`
                }}
            </button>
            <div
                v-if="!isReply"
                v-show="repliesExpanded || target"
                :id="`replies-${comment.id}`"
                class="comment-replies"
            >
                <CommentThreadRow
                    v-for="child in repliesExpanded ? replies : []"
                    :key="child.id"
                    :comment="child"
                    :uuid="uuid"
                    :can-interact="canInteract"
                    :blocked-reason="blockedReason"
                    is-reply
                    @report="emit('report', $event)"
                    @reply="target = $event"
                />
                <form
                    v-if="target"
                    ref="replyForm"
                    class="comment-reply-form"
                    @submit.prevent="submit"
                >
                    <label :for="`reply-${comment.id}`"
                        >回复 @{{ target.author.name }}</label
                    ><textarea
                        :id="`reply-${comment.id}`"
                        v-model="content"
                        minlength="1"
                        maxlength="1000"
                        rows="3"
                        required
                    ></textarea>
                    <CommentEmojiPicker
                        v-model="content"
                        :target-id="`reply-${comment.id}`"
                    />
                    <div>
                        <button type="button" @click="target = null">
                            取消</button
                        ><button type="submit" :disabled="sending">
                            {{ sending ? '提交中' : '发送回复' }}
                        </button>
                    </div>
                </form>
            </div>
            <button
                v-if="!isReply && repliesExpanded && more"
                type="button"
                class="comment-load-replies"
                :disabled="loading"
                @click="load"
            >
                {{ loading ? '正在加载' : '加载更多回复' }}
            </button>
        </div>
    </div>
</template>
