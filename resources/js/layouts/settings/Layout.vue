<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    KeyRound,
    Palette,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
}

interface FooterPayload {
    copyright_text: string;
    icp_text: string | null;
    links: NavigationItem[];
    social_links: NavigationItem[];
}

interface SettingsShellPayload {
    site: SitePayload;
    navigation: NavigationItem[];
    footer: FooterPayload;
}

const fallbackShell: SettingsShellPayload = {
    site: {
        name: '梅西影像档案库',
        logo_url: null,
        search_placeholder: '搜索图片、相册、赛事或年份',
    },
    navigation: [
        { label: '首页', url: '/' },
        { label: '图库', url: '/photos' },
        { label: '相册', url: '/albums' },
        { label: '专题', url: '/topics' },
    ],
    footer: {
        copyright_text: '梅西影像档案库',
        icp_text: null,
        links: [],
        social_links: [],
    },
};

const sidebarNavItems: NavItem[] = [
    {
        title: '个人资料',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: '修改密码',
        href: editPassword(),
        icon: KeyRound,
    },
    {
        title: '安全验证',
        href: show(),
        icon: ShieldCheck,
    },
    {
        title: '外观设置',
        href: editAppearance(),
        icon: Palette,
    },
];

const page = usePage();
const settingsShell = computed(
    () =>
        (page.props.settingsShell as SettingsShellPayload | undefined) ??
        fallbackShell,
);
const currentPath =
    typeof window !== 'undefined' ? window.location.pathname : '';
</script>

<template>
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="settingsShell.navigation"
            :site="settingsShell.site"
        />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Account</p>
                <h1 class="text-4xl font-semibold sm:text-5xl">账号设置</h1>
                <p
                    class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                >
                    维护个人资料、登录密码、安全验证和显示偏好。
                </p>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[260px_minmax(0,1fr)]"
            >
                <aside class="space-y-4">
                    <Button
                        variant="ghost"
                        class="px-0 text-[#5f6c7b] hover:text-[#094067]"
                        as-child
                    >
                        <Link href="/me">
                            <ArrowLeft class="h-4 w-4" />
                            返回个人中心
                        </Link>
                    </Button>

                    <div
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm"
                    >
                        <Heading
                            title="账号设置"
                            description="选择需要维护的账号项目"
                        />

                        <Separator class="my-4" />

                        <nav
                            class="flex flex-col space-y-1 space-x-0"
                            aria-label="账号设置导航"
                        >
                            <Button
                                v-for="item in sidebarNavItems"
                                :key="toUrl(item.href)"
                                variant="ghost"
                                :class="[
                                    'w-full justify-start',
                                    {
                                        'bg-[#d8eefe] text-[#094067]':
                                            urlIsActive(item.href, currentPath),
                                    },
                                ]"
                                as-child
                            >
                                <Link :href="item.href">
                                    <component
                                        :is="item.icon"
                                        class="h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                            </Button>
                        </nav>
                    </div>
                </aside>

                <section
                    class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm md:p-8"
                >
                    <div class="max-w-2xl space-y-12">
                        <slot />
                    </div>
                </section>
            </div>
        </section>
    </main>
</template>
