<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

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
}

defineProps<{
    footer: FooterPayload;
    site: SitePayload;
}>();

const isExternalUrl = (url: string) =>
    /^https?:\/\//.test(url) || url.startsWith('mailto:');
</script>

<template>
    <footer class="bg-[#fffffe] px-4 py-10 text-[#5f6c7b] sm:px-6 lg:px-8">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-8 border-t border-[#90b4ce]/40 pt-8 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <p class="font-semibold text-[#094067]">{{ site.name }}</p>
                <p class="mt-2 text-sm">{{ footer.copyright_text }}</p>
                <p v-if="footer.icp_text" class="mt-1 text-sm">
                    {{ footer.icp_text }}
                </p>
            </div>

            <div
                class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end"
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
