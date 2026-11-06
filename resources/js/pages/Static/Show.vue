<script setup lang="ts">
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

const props = defineProps<{
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

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
const navigationOpen = ref(false);
let desktop: MediaQueryList | undefined;
const resetNavigation = () => {
    navigationOpen.value = desktop?.matches ?? false;
};
onMounted(() => {
    desktop = window.matchMedia('(min-width: 768px)');
    resetNavigation();
    desktop.addEventListener('change', resetNavigation);
});
watch(() => props.staticPage.page.key, resetNavigation);
onBeforeUnmount(() => desktop?.removeEventListener('change', resetNavigation));
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <SeoHead :seo="staticPage.seo" />
    <main ref="pageRoot" class="archive-browser archive-reading">
        <PublicHeader
            :site="staticPage.site"
            :navigation="staticPage.navigation"
            theme="afa"
        />
        <div class="reading-layout">
            <aside class="reading-sidebar">
                <div class="desktop-site-navigation">
                    <h2>站点说明</h2>
                    <nav class="reading-nav" aria-label="站点说明页面">
                        <Link
                            v-for="link in staticPage.page_links"
                            :key="link.url"
                            :href="link.url"
                            :aria-current="
                                isCurrentPage(link, staticPage.page.key)
                                    ? 'page'
                                    : undefined
                            "
                            >{{ link.label }}</Link
                        >
                    </nav>
                </div>
                <details
                    :open="navigationOpen"
                    @toggle="
                        navigationOpen = ($event.target as HTMLDetailsElement)
                            .open
                    "
                >
                    <summary>
                        <span>站点说明</span
                        ><span class="mobile-change"
                            ><span class="open-label">切换页面</span
                            ><span class="close-label">收起导航</span></span
                        >
                    </summary>
                    <nav class="reading-nav" aria-label="站点说明页面">
                        <Link
                            v-for="link in staticPage.page_links"
                            :key="link.url"
                            :href="link.url"
                            :aria-current="
                                isCurrentPage(link, staticPage.page.key)
                                    ? 'page'
                                    : undefined
                            "
                            >{{ link.label }}</Link
                        >
                    </nav>
                </details>
            </aside>
            <article class="reading-article">
                <header class="reading-heading browser-heading">
                    <p class="eyebrow">{{ staticPage.page.eyebrow }}</p>
                    <h1>{{ staticPage.page.title }}</h1>
                    <p class="description">{{ staticPage.page.description }}</p>
                </header>
                <p class="reading-notice">{{ staticPage.page.notice }}</p>
                <section
                    v-for="section in staticPage.page.sections"
                    :key="section.title"
                    class="reading-section"
                >
                    <h2>{{ section.title }}</h2>
                    <ul>
                        <li v-for="item in section.items" :key="item">
                            {{ item }}
                        </li>
                    </ul>
                </section>
                <section
                    v-if="staticPage.page.key === 'takedown'"
                    class="reading-contact"
                >
                    <h2>联系入口</h2>
                    <template v-if="staticPage.page.contact_email"
                        ><p>
                            请发送邮件至以下邮箱，并附上需要处理的页面链接和权利说明。
                        </p>
                        <a
                            class="contact-address"
                            :href="`mailto:${staticPage.page.contact_email}`"
                            >{{ staticPage.page.contact_email }}</a
                        ><a
                            class="mail-action"
                            :href="`mailto:${staticPage.page.contact_email}`"
                            >发送邮件</a
                        ></template
                    >
                    <p v-else class="email-missing">
                        当前站点尚未配置公开联系邮箱。上线前请在后台系统设置中补充联系邮箱，或先通过站点管理员预留联系方式处理。
                    </p>
                </section>
            </article>
        </div>
    </main>
</template>
<style scoped src="./Show.css"></style>
