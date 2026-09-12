import '../css/app.css';

import PublicFooter from '@/components/PublicFooter.vue';
import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { computed, createApp, defineComponent, h } from 'vue';
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
            setup() {
                const page = usePage();
                const showFooter = computed(() => {
                    const path = page.url.split('?')[0].replace(/\/+$/, '') || '/';

                    return path !== '/me' && !path.startsWith('/me/');
                });

                return () => {
                    const publicShell = (
                        page.props as typeof page.props & {
                            publicShell?: PublicShellPayload;
                        }
                    ).publicShell;

                    return h('div', [
                        h(App, props),
                        showFooter.value && publicShell
                            ? h(PublicFooter, {
                                  footer: publicShell.footer,
                                  site: { name: publicShell.site.name },
                              })
                            : null,
                    ]);
                };
            },
        });

        createApp(PublicApp).use(plugin).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
