<script setup lang="ts">
import { shareRequest } from '@/lib/sharing';
import { usePage } from '@inertiajs/vue3';
import { Copy, ExternalLink, Image, QrCode, Share2, X } from 'lucide-vue-next';
import { toDataURL } from 'qrcode';
import { computed, onUnmounted, ref, watch } from 'vue';
const props = defineProps<{
    type: 'home' | 'photo' | 'album' | 'topic';
    targetKey: string;
    title: string;
    blockedReason?: string | null;
}>();
const page = usePage();
const loggedIn = computed(
    () => !!(page.props.auth as { user?: unknown } | undefined)?.user,
);
const dialog = ref<HTMLDialogElement | null>(null);
const busy = ref(false);
const preparing = ref(false);
const status = ref('');
const link = ref('');
const qr = ref('');
const poster = ref('');
const posterState = ref('none');
const posterCode = ref('');
interface ShareLinkResult {
    code: string;
    url: string;
    title: string;
}
const prepared = ref<
    Partial<Record<'native_share' | 'copy_link', ShareLinkResult>>
>({});
let poll: ReturnType<typeof setTimeout> | null = null;
let generation = 0;
function reset() {
    generation++;
    if (poll) clearTimeout(poll);
    busy.value = false;
    preparing.value = false;
    link.value = '';
    prepared.value = {};
    qr.value = '';
    poster.value = '';
    status.value = '';
    posterState.value = 'none';
}
watch(
    () => [props.type, props.targetKey],
    () => {
        reset();
        dialog.value?.close();
    },
);
onUnmounted(reset);
function open() {
    dialog.value?.showModal();
    status.value = props.blockedReason ?? '';
    if (!props.blockedReason) void prepare();
}
async function prepare() {
    if (preparing.value) return;
    preparing.value = true;
    const current = generation;
    await Promise.all(
        (['copy_link', 'native_share'] as const).map(async (channel) => {
            if (prepared.value[channel]) return;
            try {
                const result = await shareRequest<ShareLinkResult>(
                    '/sharing/links',
                    { type: props.type, key: props.targetKey, channel },
                );
                if (current === generation) prepared.value[channel] = result;
            } catch (error) {
                if (current === generation)
                    status.value =
                        error instanceof Error
                            ? error.message
                            : '链接准备失败，请重试';
            }
        }),
    );
    if (current === generation) preparing.value = false;
}
async function record(code: string) {
    try {
        await shareRequest(`/sharing/${code}/operation`, {});
    } catch {
        status.value += '；操作统计未保存，不影响链接使用';
    }
}
async function act(
    channel: 'copy_link' | 'native_share' | 'weibo' | 'wechat_qr' | 'poster',
) {
    if (busy.value) return;
    if (props.blockedReason) {
        status.value = props.blockedReason;
        return;
    }
    busy.value = true;
    status.value = '';
    const current = generation;
    // 微博窗口在用户点击时打开，避免异步请求后被浏览器阻止。
    const popup =
        channel === 'weibo' ? window.open('about:blank', '_blank') : null;
    if (popup) popup.opener = null;
    try {
        const cached =
            channel === 'copy_link' || channel === 'native_share'
                ? prepared.value[channel]
                : null;
        // 系统分享及剪贴板直接在点击事件中调用，保留浏览器的瞬时用户授权。
        const result =
            cached ??
            (await shareRequest<ShareLinkResult>('/sharing/links', {
                type: props.type,
                key: props.targetKey,
                channel,
            }));
        if (current !== generation) {
            popup?.close();
            return;
        }
        link.value = result.url;
        if (channel === 'copy_link') {
            if (!navigator.clipboard?.writeText) {
                status.value = '请选中下方链接手动复制';
                return;
            }
            await navigator.clipboard.writeText(result.url);
            status.value = '链接已复制';
        } else if (channel === 'native_share') {
            if (!navigator.share) {
                status.value = '当前设备不支持系统分享，请复制下方链接';
                return;
            }
            await navigator.share({ title: result.title, url: result.url });
            status.value = '已调用系统分享';
        } else if (channel === 'weibo') {
            if (!popup) {
                status.value = '微博窗口被拦截，请复制下方链接';
                return;
            }
            popup.location.href =
                'https://service.weibo.com/share/share.php?' +
                new URLSearchParams({ title: result.title, url: result.url });
            status.value = '已打开微博分享';
        } else if (channel === 'wechat_qr') {
            qr.value = await toDataURL(result.url, { width: 240, margin: 4 });
            status.value = '用微信扫一扫打开，再分享给朋友';
        } else {
            posterCode.value = result.code;
            const output = await shareRequest<PosterResult>(
                `/sharing/${result.code}/poster`,
                {},
            );
            status.value = '海报已提交生成；生成完成后可保存';
            polls = 0;
            applyPoster(output, current);
        }
        await record(result.code);
    } catch (error) {
        popup?.close();
        status.value =
            error instanceof Error && error.name === 'AbortError'
                ? '已取消分享'
                : error instanceof Error
                  ? error.message
                  : '分享操作失败，请重试';
    } finally {
        if (current === generation) busy.value = false;
    }
}
interface PosterResult {
    status: string;
    image_url: string | null;
    error: string | null;
    worker_available: boolean;
}
let polls = 0;
function applyPoster(output: PosterResult, current: number) {
    if (current !== generation) return;
    posterState.value = output.status;
    if (output.status === 'pending' && !output.worker_available)
        status.value = '处理服务尚未连接，任务已保留，服务恢复后会继续生成';
    poster.value = output.image_url ?? '';
    if (output.status === 'failed')
        status.value = output.error ?? '海报生成失败，请重试';
    if (output.status === 'ready') status.value = '海报已生成，可保存图片';
    if (output.status === 'pending' && polls++ < 40)
        poll = setTimeout(() => void refreshPoster(current), 3000);
    else polls = 0;
}
async function refreshPoster(current = generation) {
    try {
        applyPoster(
            await shareRequest<PosterResult>(
                `/sharing/${posterCode.value}/poster`,
            ),
            current,
        );
    } catch (error) {
        status.value = error instanceof Error ? error.message : '读取进度失败';
    }
}
</script>
<template>
    <button
        type="button"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-sm border border-[#90b4ce]/60 px-4 text-sm font-semibold text-[#094067] hover:border-[#3da9fc] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#3da9fc]"
        @click="open"
    >
        <Share2 class="h-4 w-4" aria-hidden="true" />分享
    </button>
    <dialog
        ref="dialog"
        class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-sm border border-[#90b4ce]/40 bg-[#fffffe] p-6 text-[#094067] shadow-xl backdrop:bg-black/40"
        aria-label="分享内容"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold">分享这份影像记忆</h2>
                <p class="mt-2 text-sm break-words text-[#5f6c7b]">
                    {{ title }}
                </p>
            </div>
            <button
                class="flex h-11 w-11 shrink-0 items-center justify-center"
                aria-label="关闭分享"
                @click="dialog?.close()"
            >
                <X aria-hidden="true" />
            </button>
        </div>
        <p class="my-4 text-sm text-[#5f6c7b]">
            {{
                loggedIn
                    ? '通过你的链接带来有效访问，可累计推广勋章进度。'
                    : '登录后分享，可累计个人推广进度。游客也可以分享。'
            }}
        </p>
        <div class="divide-y divide-[#90b4ce]/30">
            <button
                v-for="item in [
                    { key: 'copy_link', label: '复制链接', icon: Copy },
                    { key: 'native_share', label: '系统分享', icon: Share2 },
                    { key: 'weibo', label: '微博分享', icon: ExternalLink },
                    { key: 'wechat_qr', label: '微信二维码', icon: QrCode },
                    { key: 'poster', label: '生成分享海报', icon: Image },
                ] as const"
                :key="item.key"
                type="button"
                :disabled="
                    busy ||
                    !!blockedReason ||
                    ((item.key === 'copy_link' ||
                        item.key === 'native_share') &&
                        !prepared[item.key])
                "
                class="flex min-h-12 w-full items-center gap-3 py-3 text-left font-medium hover:text-[#3da9fc] disabled:opacity-50"
                @click="act(item.key)"
            >
                <component
                    :is="item.icon"
                    class="h-5 w-5"
                    aria-hidden="true"
                />{{ item.label }}
            </button>
        </div>
        <p role="status" aria-live="polite" class="mt-3 text-sm">
            {{ busy ? '正在准备…' : status }}
        </p>
        <button
            v-if="
                !blockedReason &&
                (!prepared.copy_link || !prepared.native_share)
            "
            type="button"
            :disabled="preparing"
            class="mt-3 min-h-11 text-sm underline disabled:opacity-50"
            @click="prepare"
        >
            {{ preparing ? '正在准备分享链接…' : '重新准备分享链接' }}
        </button>
        <label v-if="link" class="mt-4 block text-sm"
            >分享链接<input
                :value="link"
                readonly
                class="mt-2 w-full rounded-sm border border-[#90b4ce] bg-white px-3 py-2 text-sm"
                @focus="($event.target as HTMLInputElement).select()"
        /></label>
        <img
            v-if="qr"
            :src="qr"
            alt="扫码访问分享内容"
            class="mx-auto mt-4 h-60 w-60 max-w-full"
        />
        <div v-if="posterState === 'pending'" class="mt-4 text-sm">
            海报正在队列中生成。<button
                class="underline"
                @click="refreshPoster()"
            >
                刷新进度
            </button>
        </div>
        <div v-if="poster" class="mt-4">
            <img
                :src="poster"
                alt="分享海报预览"
                class="w-full rounded-sm border border-[#90b4ce]/30"
            /><a
                :href="poster"
                download="分享海报.png"
                class="mt-3 inline-flex min-h-11 items-center rounded-sm bg-[#3da9fc] px-4 font-semibold text-white"
                >保存海报</a
            >
            <p class="mt-2 text-xs text-[#5f6c7b]">
                手机无法直接保存时，可长按图片保存。
            </p>
        </div>
    </dialog>
</template>
