<script setup lang="ts">
import FooterSocialLinks from '@/components/FooterSocialLinks.vue';
import { Link } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface FooterPayload {
    copyright_text: string;
    icp_text: string | null;
    links: NavigationItem[];
    social_links: NavigationItem[];
}

interface SitePayload {
    name: string;
    logo_url?: string | null;
}

const props = withDefaults(
    defineProps<{
        footer: FooterPayload;
        site: SitePayload;
        theme?: 'default' | 'archive';
        pageUrl?: string;
    }>(),
    { theme: 'default', pageUrl: '' },
);

// 只对已知合规页面分组，其他后台链接保持在站点信息中。
const linkGroups = computed(() => {
    const legalPaths = new Set([
        '/copyright',
        '/takedown',
        '/privacy',
        '/terms',
    ]);
    return [
        {
            title: '站点信息',
            links: props.footer.links.filter(
                (link) => !legalPaths.has(link.url),
            ),
            social: false,
        },
        {
            title: '版权与使用',
            links: props.footer.links.filter((link) =>
                legalPaths.has(link.url),
            ),
            social: false,
        },
        { title: '关注我们', links: props.footer.social_links, social: true },
    ].filter((group) => group.links.length);
});

const footerRoot = ref<HTMLElement | null>(null);
let motion: gsap.MatchMedia | undefined;

const setupMotion = async () => {
    motion?.revert();
    await nextTick();
    if (props.theme !== 'archive' || !footerRoot.value) return;

    motion = gsap.matchMedia();
    motion.add(
        '(prefers-reduced-motion: no-preference)',
        () => {
            const entrance = gsap.from('.footer-reveal', {
                paused: true,
                immediateRender: false,
                y: 8,
                opacity: 0,
                duration: 0.4,
                stagger: 0.05,
                ease: 'power2.out',
                clearProps: 'transform,opacity',
            });
            // 结果数量变化会改变页脚位置，进入视口时再播放，不预先隐藏内容。
            const observer = new IntersectionObserver(
                (entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        entrance.play();
                        observer.disconnect();
                    }
                },
                { threshold: 0.05 },
            );
            observer.observe(footerRoot.value!);
            return () => observer.disconnect();
        },
        footerRoot.value,
    );
};

onMounted(setupMotion);
watch([() => props.theme, () => props.pageUrl], setupMotion);
onUnmounted(() => motion?.revert());

const isExternalUrl = (url: string) =>
    /^https?:\/\//.test(url) || url.startsWith('mailto:');
</script>

<template>
    <footer
        v-if="theme === 'archive'"
        ref="footerRoot"
        class="archive-footer"
        aria-label="站点信息"
    >
        <div class="archive-footer-inner">
            <div class="archive-footer-main">
                <div class="archive-footer-brand footer-reveal">
                    <div class="archive-footer-identity">
                        <span
                            v-if="site.logo_url"
                            class="archive-footer-mark"
                            :style="{ maskImage: `url(${site.logo_url})` }"
                            aria-hidden="true"
                        />
                        <p>{{ site.name }}</p>
                    </div>
                    <p class="archive-footer-description">
                        整理梅西影像，留存生涯记忆。
                    </p>
                    <p class="archive-footer-note">
                        独立影像档案项目，非梅西官方站。
                    </p>
                    <FooterSocialLinks />
                </div>
                <div class="archive-footer-directory footer-reveal">
                    <nav
                        v-for="group in linkGroups"
                        :key="group.title"
                        :aria-label="group.social ? '社媒链接' : group.title"
                    >
                        <h2>{{ group.title }}</h2>
                        <ul class="archive-footer-links">
                            <li v-for="link in group.links" :key="link.label">
                                <a
                                    v-if="isExternalUrl(link.url)"
                                    :href="link.url"
                                    target="_blank"
                                    rel="noreferrer"
                                    >{{ link.label }}</a
                                >
                                <Link
                                    v-else-if="!group.social"
                                    :href="link.url"
                                    >{{ link.label }}</Link
                                >
                                <a v-else :href="link.url">{{ link.label }}</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <div class="archive-footer-legal footer-reveal">
                <p>{{ footer.copyright_text }}</p>
                <p v-if="footer.icp_text">{{ footer.icp_text }}</p>
            </div>
        </div>
    </footer>
    <footer
        v-else
        class="public-footer bg-[#fffffe] px-4 py-10 text-[#5f6c7b] sm:px-6 lg:px-8"
    >
        <div
            class="footer-layout mx-auto flex max-w-7xl flex-col gap-8 border-t border-[#90b4ce]/40 pt-8 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="footer-brand">
                <p class="font-semibold text-[#094067]">{{ site.name }}</p>
                <p class="mt-2 text-sm">{{ footer.copyright_text }}</p>
                <p v-if="footer.icp_text" class="mt-1 text-sm">
                    {{ footer.icp_text }}
                </p>
                <FooterSocialLinks />
            </div>

            <div
                class="footer-navigation flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end"
            >
                <nav
                    v-if="footer.links.length"
                    class="flex flex-wrap gap-4 text-sm"
                    aria-label="页脚链接"
                >
                    <template v-for="link in footer.links" :key="link.label">
                        <a
                            v-if="isExternalUrl(link.url)"
                            :href="link.url"
                            class="hover:text-[#3da9fc]"
                            target="_blank"
                            rel="noreferrer"
                        >
                            {{ link.label }}
                        </a>
                        <Link
                            v-else
                            :href="link.url"
                            class="hover:text-[#3da9fc]"
                        >
                            {{ link.label }}
                        </Link>
                    </template>
                </nav>
                <nav
                    v-if="footer.social_links.length"
                    class="flex flex-wrap gap-4 text-sm"
                    aria-label="社媒链接"
                >
                    <a
                        v-for="link in footer.social_links"
                        :key="link.label"
                        :href="link.url"
                        class="hover:text-[#3da9fc]"
                        :target="isExternalUrl(link.url) ? '_blank' : undefined"
                        :rel="
                            isExternalUrl(link.url) ? 'noreferrer' : undefined
                        "
                    >
                        {{ link.label }}
                    </a>
                </nav>
            </div>
        </div>
    </footer>
</template>

<style scoped>
/* 首页页脚以正常字号和紧凑分组呈现站点身份与合规信息。 */
.archive-footer {
    --footer-ink: #042240;
    --footer-sky: #98cef0;
    --footer-gold: #ba9f6c;
    --footer-muted: #526779;
    color: var(--footer-ink);
    background: #ffffff;
    font-family:
        'Instrument Sans', 'PingFang SC', 'Microsoft YaHei', system-ui,
        sans-serif;
}
.archive-footer-inner {
    width: min(100% - 64px, 1280px);
    margin-inline: auto;
    padding-right: 64px;
}
.archive-footer-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 64px;
    padding-block: 40px 28px;
}
.archive-footer-identity {
    display: flex;
    align-items: center;
    gap: 16px;
}
.archive-footer-mark {
    width: 56px;
    height: 48px;
    display: block;
    flex-shrink: 0;
    background: var(--footer-gold);
    mask-size: contain;
    mask-repeat: no-repeat;
    mask-position: center;
}
.archive-footer-identity p {
    font-size: 23px;
    font-weight: 600;
    line-height: 1.5;
    overflow-wrap: anywhere;
}
.archive-footer-description {
    margin-top: 16px;
    font-size: 15px;
    line-height: 1.8;
}
.archive-footer-note {
    margin-top: 4px;
    color: var(--footer-muted);
    font-size: 13px;
    line-height: 1.8;
}
.archive-footer-directory {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 24px;
}
.archive-footer-directory h2 {
    margin-bottom: 10px;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.8;
}
.archive-footer-links {
    display: flex;
    flex-direction: column;
    list-style: none;
    padding: 0;
    margin: 0;
}
.archive-footer-links a {
    display: inline-flex;
    align-items: center;
    min-height: 36px;
    color: var(--footer-muted);
    font-size: 14px;
    line-height: 1.6;
    text-underline-offset: 5px;
    transition: color 160ms ease;
}
.archive-footer-links a:focus-visible {
    color: var(--footer-ink);
    outline: 2px solid var(--footer-ink);
    outline-offset: 3px;
}
.archive-footer-links a:active {
    color: var(--footer-ink);
    text-decoration: underline;
}
.archive-footer-legal {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px 24px;
    padding-block: 18px 24px;
    border-top: 1px solid #04224018;
    color: var(--footer-muted);
    font-size: 12px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}
@media (hover: hover) {
    .archive-footer-links a:hover {
        color: var(--footer-ink);
        text-decoration: underline;
        text-decoration-color: var(--footer-gold);
    }
}
@media (max-width: 1023px) {
    .archive-footer-main {
        grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
        gap: 32px;
    }
    .archive-footer-identity {
        align-items: flex-start;
    }
    .archive-footer-identity p {
        font-size: 20px;
    }
    .archive-footer-directory {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }
}
@media (max-width: 767px) {
    .archive-footer-inner {
        width: calc(100% - 40px);
        padding-right: 0;
        padding-bottom: calc(72px + env(safe-area-inset-bottom));
    }
    .archive-footer-main {
        grid-template-columns: minmax(0, 1fr);
        padding-block: 28px 20px;
        gap: 24px;
    }
    .archive-footer-identity {
        align-items: center;
        gap: 12px;
    }
    .archive-footer-mark {
        width: 44px;
        height: 38px;
    }
    .archive-footer-description {
        margin-top: 12px;
        font-size: 14px;
    }
    .archive-footer-links a {
        min-height: 44px;
    }
    .archive-footer-legal {
        flex-direction: column;
        padding-block: 16px 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .archive-footer-links a {
        transition: none;
    }
}
</style>
