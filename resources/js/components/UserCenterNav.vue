<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    Flag,
    Heart,
    HeartHandshake,
    KeyRound,
    Medal,
    MessageCircle,
    Palette,
    Settings,
    Share2,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

interface SummaryPayload {
    favorites_count: number;
    comments_count: number;
    corrections_count: number;
    reports_count: number;
    pending_count: number;
    unread_notifications_count: number;
}

interface UserPayload {
    name: string;
    avatar?: string | null;
    email: string;
    status_label: string;
    is_banned: boolean;
    banned_until: string | null;
    ban_reason: string | null;
}

const props = withDefaults(
    defineProps<{
        user: UserPayload;
        summary: SummaryPayload;
        variant?: 'default' | 'archive';
        active:
            | 'shares'
            | 'submissions'
            | 'overview'
            | 'favorites'
            | 'comments'
            | 'reports'
            | 'sponsorships'
            | 'badge-hall'
            | 'badges'
            | 'notifications'
            | '/settings/profile'
            | '/settings/password'
            | '/settings/appearance'
            | '/settings/two-factor';
    }>(),
    { variant: 'default' },
);

const navigationOpen = ref(false);
const failedAvatar = ref<string | null>(null);
const settingsOpen = ref(true);
let desktop: MediaQueryList | undefined;
const syncNavigation = () => {
    navigationOpen.value = desktop?.matches ?? false;
};
onMounted(() => {
    if (props.variant !== 'archive') return;
    desktop = window.matchMedia('(min-width: 768px)');
    syncNavigation();
    desktop.addEventListener('change', syncNavigation);
});
onUnmounted(() => desktop?.removeEventListener('change', syncNavigation));

const items = [
    { key: 'overview', label: '个人首页', url: '/me', icon: UserRound },
    {
        key: 'submissions',
        label: '图片投稿',
        url: '/me/submissions',
        icon: Medal,
    },
    { key: 'shares', label: '分享推广', url: '/me/shares', icon: Share2 },
    { key: 'favorites', label: '我的收藏', url: '/me/favorites', icon: Heart },
    {
        key: 'comments',
        label: '我的评论',
        url: '/me/comments',
        icon: MessageCircle,
    },
    { key: 'reports', label: '我的举报', url: '/me/reports', icon: Flag },
    {
        key: 'sponsorships',
        label: '我的赞助',
        url: '/me/sponsorships',
        icon: HeartHandshake,
    },
    {
        key: 'badge-hall',
        label: '勋章大厅',
        url: '/me/badges/hall',
        icon: Medal,
    },
    { key: 'badges', label: '我的勋章', url: '/me/badges', icon: Medal },
    {
        key: 'notifications',
        label: '通知中心',
        url: '/me/notifications',
        icon: Bell,
    },
] as const;

const settingsItems = [
    { label: '个人资料', url: '/settings/profile', icon: UserRound },
    { label: '修改密码', url: '/settings/password', icon: KeyRound },
    { label: '安全验证', url: '/settings/two-factor', icon: ShieldCheck },
    { label: '外观设置', url: '/settings/appearance', icon: Palette },
] as const;
</script>

<template>
    <aside v-if="variant === 'archive'" class="center-sidebar">
        <div class="identity">
            <span class="avatar" aria-hidden="true">
                <img
                    v-if="user.avatar && failedAvatar !== user.avatar"
                    :src="user.avatar"
                    alt=""
                    @error="failedAvatar = user.avatar ?? null"
                />
                <template v-else>{{
                    user.name.slice(0, 1).toUpperCase()
                }}</template>
            </span>
            <div>
                <strong>{{ user.name }}</strong
                ><span>{{ user.email }}</span>
            </div>
        </div>
        <p
            v-if="user.is_banned"
            class="account-status restricted"
            role="status"
        >
            当前账号互动权限受限<span v-if="user.banned_until"
                >，截至 {{ user.banned_until }}</span
            ><span v-if="user.ban_reason">。原因：{{ user.ban_reason }}</span>
        </p>
        <p v-else class="account-status">账号状态：{{ user.status_label }}</p>
        <details
            id="center-menu"
            :open="navigationOpen"
            @toggle="
                navigationOpen = ($event.target as HTMLDetailsElement).open
            "
        >
            <summary>
                <span>个人中心导航</span><span class="closed">切换页面</span
                ><span class="opened">收起导航</span>
            </summary>
            <nav id="center-nav" aria-label="个人中心导航">
                <Link
                    v-for="item in items"
                    :key="item.key"
                    :href="item.url"
                    class="center-link"
                    :aria-current="active === item.key ? 'page' : undefined"
                >
                    <span>{{ item.label }}</span
                    ><span
                        v-if="
                            item.key === 'notifications' &&
                            summary.unread_notifications_count > 0
                        "
                        class="count"
                        >{{ summary.unread_notifications_count }}</span
                    >
                </Link>
            </nav>
            <details
                id="settings-group"
                :open="settingsOpen"
                @toggle="
                    settingsOpen = ($event.target as HTMLDetailsElement).open
                "
            >
                <summary>
                    <span>账号设置</span
                    ><span class="settings-closed">展开</span
                    ><span class="settings-opened">收起</span>
                </summary>
                <nav id="settings-nav" aria-label="账号设置">
                    <Link
                        v-for="item in settingsItems"
                        :key="item.url"
                        :href="item.url"
                        class="center-link"
                        :aria-current="active === item.url ? 'page' : undefined"
                        >{{ item.label }}</Link
                    >
                </nav>
            </details>
        </details>
    </aside>
    <aside v-else class="space-y-5">
        <div
            class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-sm bg-[#3da9fc] text-base font-bold text-[#fffffe]"
                >
                    {{ user.name.slice(0, 1).toUpperCase() }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-[#094067]">
                        {{ user.name }}
                    </p>
                    <p class="mt-1 truncate text-sm text-[#5f6c7b]">
                        {{ user.email }}
                    </p>
                </div>
            </div>

            <div
                v-if="user.is_banned"
                class="mt-4 rounded-sm border border-[#ef4565]/30 bg-[#fff3f6] p-3 text-sm leading-6 text-[#b91c3a]"
            >
                当前账号互动权限受限
                <span v-if="user.banned_until"
                    >，截至 {{ user.banned_until }}</span
                >
                <span v-if="user.ban_reason"
                    >。原因：{{ user.ban_reason }}</span
                >
            </div>
            <div
                v-else
                class="mt-4 rounded-sm bg-[#d8eefe] p-3 text-sm font-semibold text-[#094067]"
            >
                账号状态：{{ user.status_label }}
            </div>
        </div>

        <nav class="grid gap-2" aria-label="个人中心导航">
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.url"
                class="flex items-center justify-between rounded-sm border px-4 py-3 text-sm font-semibold transition"
                :class="
                    active === item.key
                        ? 'border-[#3da9fc] bg-[#d8eefe] text-[#094067]'
                        : 'border-[#90b4ce]/35 bg-[#fffffe] text-[#5f6c7b] hover:border-[#3da9fc] hover:text-[#094067]'
                "
            >
                <span class="flex items-center gap-2">
                    <component
                        :is="item.icon"
                        class="h-4 w-4"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </span>
                <span
                    v-if="
                        item.key === 'notifications' &&
                        summary.unread_notifications_count > 0
                    "
                    class="rounded-full bg-[#ef4565] px-2 py-0.5 text-xs text-[#fffffe]"
                >
                    {{ summary.unread_notifications_count }}
                </span>
            </Link>
        </nav>

        <div
            class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm"
        >
            <div
                class="mb-3 flex items-center gap-2 text-sm font-semibold text-[#094067]"
            >
                <Settings class="h-4 w-4" aria-hidden="true" />
                账号设置
            </div>
            <div class="grid gap-2">
                <Link
                    v-for="item in settingsItems"
                    :key="item.url"
                    :href="item.url"
                    class="flex items-center gap-2 rounded-sm px-3 py-2 text-sm font-medium text-[#5f6c7b] transition hover:bg-[#d8eefe] hover:text-[#094067]"
                >
                    <component
                        :is="item.icon"
                        class="h-4 w-4"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </Link>
            </div>
        </div>
    </aside>
</template>

<style scoped src="./UserCenterNav.css"></style>
