import '../css/app.css';

import PublicFooter from '@/components/PublicFooter.vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { computed, createApp, defineComponent, h, onUnmounted, ref } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

interface PublicShellPayload {
    site: {
        name: string;
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
            setup(componentProps: { initialPage: { props?: Record<string, unknown> } }) {
                const currentPath = ref(window.location.pathname);
                const publicShell = componentProps.initialPage.props?.publicShell as
                    | PublicShellPayload
                    | undefined;
                const removeNavigateListener = router.on('navigate', (event) => {
                    currentPath.value = new URL(event.detail.page.url, window.location.origin).pathname;
                });
                const showFooter = computed(() => {
                    const path = currentPath.value.replace(/\/+$/, '') || '/';

                    return path !== '/me' && !path.startsWith('/me/');
                });

                onUnmounted(() => removeNavigateListener());

                return () =>
                    h('div', [
                        h(App, props),
                        showFooter.value && publicShell
                            ? h(PublicFooter, {
                                  footer: publicShell.footer,
                                  site: { name: publicShell.site.name },
                              })
                            : null,
                    ]);
            },
        });

        createApp(PublicApp, { initialPage: props.initialPage }).use(plugin).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
