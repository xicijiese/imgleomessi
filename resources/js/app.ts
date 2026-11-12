import '../css/app.css';

import PublicFooter from '@/components/PublicFooter.vue';
import ShareVisitTracker from '@/components/ShareVisitTracker.vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, defineComponent, h, onUnmounted, ref } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Nginx等上游返回的HTML错误不符合Inertia协议，改用独立错误页兜底。
router.on('invalid', (event) => {
    const status = event.detail.response.status;
    if (![401, 403, 404, 405, 410, 419, 429, 500, 502, 503, 504].includes(status)) return;
    event.preventDefault();
    window.location.assign(`/errors/${status}.html`);
});

interface PublicShellPayload {
    site: {
        name: string;
        logo_url?: string | null;
    };
    footer: {
        copyright_text: string;
        icp_text: string | null;
        icp_url?: string | null;
        links: Array<{ label: string; url: string }>;
        social_links: Array<{ label: string; url: string }>;
    };
}

createInertiaApp({
    defaults: {
        visitOptions: (href, options) => {
            const supported =
                typeof document !== 'undefined' &&
                typeof document.startViewTransition === 'function';
            if (!supported) return { viewTransition: false };
            const target = new URL(href, window.location.href);

            const enabled =
                options.method === 'get' &&
                !options.async &&
                !options.only?.length &&
                target.origin === window.location.origin &&
                target.pathname !== window.location.pathname &&
                !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            return {
                viewTransition: enabled
                    ? (transition: ViewTransition) => {
                          // 多图页面快照准备过慢时直接显示新页，避免转场阻碍浏览。
                          const timeout = window.setTimeout(
                              () => transition.skipTransition(),
                              400,
                          );
                          const clear = () => window.clearTimeout(timeout);
                          transition.ready.then(clear, clear);
                      }
                    : false,
            };
        },
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const PublicApp = defineComponent({
            props: {
                initialPage: {
                    type: Object,
                    required: true,
                },
            },
            setup(componentProps: {
                initialPage: { props?: Record<string, unknown> };
            }) {
                const currentPath = ref(window.location.pathname);
                const currentPageUrl = ref(
                    window.location.pathname + window.location.search,
                );
                const publicShell = componentProps.initialPage.props
                    ?.publicShell as PublicShellPayload | undefined;
                const removeNavigateListener = router.on(
                    'navigate',
                    (event) => {
                        currentPath.value = new URL(
                            event.detail.page.url,
                            window.location.origin,
                        ).pathname;
                        currentPageUrl.value = event.detail.page.url;
                    },
                );
                onUnmounted(() => removeNavigateListener());

                return () =>
                    h('div', [
                        h(App, props),
                        h(ShareVisitTracker),
                        publicShell
                            ? h(PublicFooter, {
                                  footer: publicShell.footer,
                                  site: publicShell.site,
                                  pageUrl: currentPageUrl.value,
                                  theme:
                                      [
                                          '/',
                                          '/photos',
                                          '/search',
                                          '/search/image',
                                          '/albums',
                                          '/topics',
                                          '/teams',
                                          '/seasons',
                                          '/timeline',
                                          '/rankings',
                                          '/support',
                                          '/supporters',
                                          '/support/result',
                                          '/about',
                                          '/copyright',
                                          '/takedown',
                                          '/privacy',
                                          '/terms',
                                          '/login',
                                          '/register',
                                          '/forgot-password',
                                          '/email/verify',
                                          '/user/confirm-password',
                                          '/two-factor-challenge',
                                          '/settings/profile',
                                          '/settings/password',
                                          '/settings/appearance',
                                      ].includes(currentPath.value) ||
                                      /^\/me(?:\/|$)/.test(currentPath.value) ||
                                      /^\/(photos|albums|topics|teams|seasons|users)\/[^/]+$/.test(
                                          currentPath.value,
                                      ) ||
                                      /^\/timeline\/\d{4}(?:\/\d{2})?$/.test(
                                          currentPath.value,
                                      ) ||
                                      /^\/search\/image\/[^/]+$/.test(
                                          currentPath.value,
                                      ) ||
                                      /^\/reset-password\/[^/]+$/.test(
                                          currentPath.value,
                                      )
                                          ? 'archive'
                                          : 'default',
                              })
                            : null,
                    ]);
            },
        });

        createApp(PublicApp, { initialPage: props.initialPage })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
