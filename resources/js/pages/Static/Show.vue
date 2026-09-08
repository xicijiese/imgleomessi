<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';

interface NavigationItem {
    label: string;
    url: string;
}

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
    contact_email: string | null;
}

interface FooterPayload {
    copyright_text: string;
    icp_text: string | null;
    links: NavigationItem[];
    social_links: NavigationItem[];
}

interface StaticPageSection {
    title: string;
    items: string[];
}

interface StaticPageContent {
    key: string;
    title: string;
    eyebrow: string;
    description: string;
    notice: string;
    contact_email?: string | null;
    sections: StaticPageSection[];
}

interface StaticPagePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    seo: SeoPayload;
    footer: FooterPayload;
    page: StaticPageContent;
    page_links: NavigationItem[];
}

defineProps<{
    staticPage: StaticPagePayload;
}>();

const staticPageUrls: Record<string, string> = {
    about: '/about',
    copyright: '/copyright',
    takedown: '/takedown',
    privacy: '/privacy',
    terms: '/terms',
};

const isCurrentPage = (link: NavigationItem, pageKey: string) =>
    link.url === staticPageUrls[pageKey];
</script>

<template>
    <SeoHead :seo="staticPage.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="staticPage.navigation"
            :site="staticPage.site"
        />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-12 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                    {{ staticPage.page.eyebrow }}
                </p>
                <h1
                    class="max-w-4xl text-4xl leading-tight font-semibold sm:text-5xl"
                >
                    {{ staticPage.page.title }}
                </h1>
                <p
                    class="mt-5 max-w-3xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                >
                    {{ staticPage.page.description }}
                </p>
            </div>
        </section>

        <section class="px-4 py-10 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1fr)_280px]"
            >
                <article class="min-w-0">
                    <div
                        class="mb-8 rounded-sm border border-[#90b4ce]/45 bg-[#fffffe] px-5 py-4 text-sm leading-7 text-[#5f6c7b] shadow-sm"
                    >
                        {{ staticPage.page.notice }}
                    </div>

                    <div class="grid gap-8">
                        <section
                            v-for="section in staticPage.page.sections"
                            :key="section.title"
                            class="border-b border-[#90b4ce]/25 pb-8 last:border-b-0"
                        >
                            <h2 class="text-2xl font-semibold text-[#094067]">
                                {{ section.title }}
                            </h2>
                            <ul
                                class="mt-5 grid gap-3 text-sm leading-7 text-[#5f6c7b] sm:text-base"
                            >
                                <li
                                    v-for="item in section.items"
                                    :key="item"
                                    class="flex gap-3"
                                >
                                    <span
                                        class="mt-3 h-1.5 w-1.5 shrink-0 rounded-full bg-[#3da9fc]"
                                        aria-hidden="true"
                                    />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <div
                        v-if="staticPage.page.key === 'takedown'"
                        class="mt-8 rounded-sm bg-[#094067] px-5 py-5 text-[#d8eefe]"
                    >
                        <h2 class="text-xl font-semibold text-[#fffffe]">
                            联系入口
                        </h2>
                        <p class="mt-3 text-sm leading-7">
                            <template v-if="staticPage.page.contact_email">
                                请发送邮件至
                                <a
                                    :href="`mailto:${staticPage.page.contact_email}`"
                                    class="font-semibold text-[#3da9fc] hover:text-[#fffffe]"
                                >
                                    {{ staticPage.page.contact_email }}
                                </a>
                                ，并附上需要处理的页面链接和权利说明。
                            </template>
                            <template v-else>
                                当前站点尚未配置公开联系邮箱。上线前请在后台系统设置中补充联系邮箱，或先通过站点管理员预留联系方式处理。
                            </template>
                        </p>
                    </div>
                </article>

                <aside class="lg:pt-1">
                    <div
                        class="sticky top-24 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-[#094067]">
                            站点说明
                        </h2>
                        <nav class="mt-4 grid gap-1" aria-label="站点说明页面">
                            <Link
                                v-for="link in staticPage.page_links"
                                :key="link.url"
                                :href="link.url"
                                class="rounded-sm px-3 py-2 text-sm transition hover:bg-[#d8eefe] hover:text-[#094067]"
                                :class="
                                    isCurrentPage(link, staticPage.page.key)
                                        ? 'bg-[#d8eefe] font-semibold text-[#094067]'
                                        : 'text-[#5f6c7b]'
                                "
                            >
                                {{ link.label }}
                            </Link>
                        </nav>
                    </div>
                </aside>
            </div>
        </section>

        <PublicFooter :footer="staticPage.footer" :site="staticPage.site" />
    </main>
</template>
