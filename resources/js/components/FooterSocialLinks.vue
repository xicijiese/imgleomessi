<script setup lang="ts">
import { onMounted, onUnmounted, ref, useId } from 'vue';
import bilibiliIcon from '../../images/social/bilibili.svg';
import friendQr from '../../images/social/wechat-friend-qr.png';
import officialQr from '../../images/social/wechat-official-qr.jpg';
import wechatIcon from '../../images/social/wechat.svg';
import weiboIcon from '../../images/social/weibo.svg';

const root = ref<HTMLElement | null>(null);
const openQr = ref<string | null>(null);
const id = useId();
const channels = [
    {
        key: 'wechat',
        label: '微信',
        icon: wechatIcon,
        qr: friendQr,
        alt: '添加微信好友二维码',
        caption: '添加好友请注明来意',
    },
    {
        key: 'weibo',
        label: '微博',
        icon: weiboIcon,
        url: 'https://weibo.com/u/5250260756',
    },
    {
        key: 'bilibili',
        label: 'B站',
        icon: bilibiliIcon,
        url: 'https://space.bilibili.com/25231526',
    },
    {
        key: 'official',
        label: '微信公众号',
        icon: wechatIcon,
        qr: officialQr,
        alt: '微信公众号二维码',
        caption: '扫码关注微信公众号',
    },
];

const closeOutside = (event: PointerEvent) => {
    if (!root.value?.contains(event.target as Node)) openQr.value = null;
};
const closeOnBlur = (event: FocusEvent) => {
    if (
        !(event.relatedTarget instanceof Node) ||
        !(event.currentTarget instanceof HTMLElement) ||
        !event.currentTarget.contains(event.relatedTarget)
    )
        openQr.value = null;
};

onMounted(() => document.addEventListener('pointerdown', closeOutside));
onUnmounted(() => document.removeEventListener('pointerdown', closeOutside));
</script>

<template>
    <nav
        ref="root"
        class="footer-socials"
        aria-label="社交媒体"
        @keydown.esc.stop="openQr = null"
    >
        <template v-for="channel in channels" :key="channel.key">
            <a
                v-if="channel.url"
                class="footer-social-button"
                :href="channel.url"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="`${channel.label}（新窗口打开）`"
            >
                <span
                    class="footer-social-icon"
                    :style="{ maskImage: `url(${channel.icon})` }"
                    aria-hidden="true"
                />
                <span>{{ channel.label }}</span>
            </a>
            <div
                v-else
                class="footer-social-qr"
                @pointerenter="
                    (event) => {
                        if (event.pointerType === 'mouse') openQr = channel.key;
                    }
                "
                @pointerleave="openQr = null"
                @focusout="closeOnBlur"
            >
                <button
                    type="button"
                    class="footer-social-button"
                    :aria-label="channel.label"
                    :aria-expanded="openQr === channel.key"
                    :aria-controls="`${id}-${channel.key}`"
                    @focus="openQr = channel.key"
                    @click="openQr = channel.key"
                >
                    <span
                        class="footer-social-icon"
                        :class="{
                            'official-account-icon': channel.key === 'official',
                        }"
                        :style="{ maskImage: `url(${channel.icon})` }"
                        aria-hidden="true"
                    />
                    <span>{{
                        channel.key === 'official' ? '公众号' : channel.label
                    }}</span>
                </button>
                <div
                    v-show="openQr === channel.key"
                    :id="`${id}-${channel.key}`"
                    class="footer-social-popover"
                    role="region"
                    :aria-label="channel.alt"
                >
                    <img
                        :src="channel.qr"
                        :alt="channel.alt"
                        width="216"
                        height="216"
                    />
                    <p>{{ channel.caption }}</p>
                </div>
            </div>
        </template>
    </nav>
</template>

<style scoped>
.footer-socials {
    display: flex;
    align-items: center;
    gap: 20px;
    width: fit-content;
    margin-top: 18px;
    color: var(--footer-ink, #042240);
}
.footer-social-button {
    display: flex;
    width: 44px;
    min-height: 58px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 6px;
    color: inherit;
    border-radius: 6px;
    background: transparent;
    font-size: 11px;
    line-height: 1.5;
    text-decoration: none;
    cursor: pointer;
}
.footer-social-icon {
    display: block;
    width: 22px;
    height: 22px;
    background: currentColor;
    mask-size: contain;
    mask-repeat: no-repeat;
    mask-position: center;
}
.official-account-icon {
    width: 26px;
    height: 26px;
}
.footer-social-qr {
    position: relative;
}
.footer-social-popover {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 0;
    z-index: 50;
    width: 244px;
    padding: 14px;
    border: 1px solid var(--footer-gold, #ba9f6c);
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 8px 28px #04224024;
}
/* 透明连接区让鼠标可以从图标移入二维码而不关闭。 */
.footer-social-popover::after {
    content: '';
    position: absolute;
    inset: auto 0 -12px;
    height: 12px;
}
.footer-social-qr:last-child .footer-social-popover {
    right: 0;
    left: auto;
}
.footer-social-popover img {
    display: block;
    width: 100%;
    height: auto;
    background: #ffffff;
}
.footer-social-popover p {
    margin: 12px 0 0;
    color: var(--footer-ink, #042240);
    font-size: 13px;
    line-height: 1.6;
    text-align: center;
}
.footer-social-button[aria-expanded='true'],
.footer-social-button:active {
    background: var(--footer-gold, #ba9f6c);
}
.footer-social-button:focus-visible {
    outline: 2px solid var(--footer-ink, #042240);
    outline-offset: 3px;
}
@media (hover: hover) {
    .footer-social-button:hover {
        background: var(--footer-gold, #ba9f6c);
    }
}
@media (max-width: 767px) {
    .footer-socials {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        width: 100%;
        margin-top: 20px;
    }
    .footer-social-button {
        width: 100%;
        min-height: 64px;
        border: 1px solid #dce5ec;
        font-size: 12px;
    }
    .footer-social-qr {
        min-width: 0;
    }
    .footer-social-popover {
        max-width: calc(100vw - 40px);
    }
}
</style>
