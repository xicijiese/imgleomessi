import '../css/app.css';

import PublicFooter from '@/components/PublicFooter.vue';
import ShareVisitTracker from '@/components/ShareVisitTracker.vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, defineComponent, h, onUnmounted, ref } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

interface PublicShellPayload {
    site: {
        name: string;
        logo_url?: string | null;
    };
    footer: {
        copyright_text: string;
        icp_text: string | null;
        links: Array<{ label: string; url: string }>;
        social_links: Array<{ label: string; url: string }>;
    };
}

createInertiaApp({
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
