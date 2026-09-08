<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Medal } from 'lucide-vue-next';

interface NavigationItem {
    label: string;
    url: string;
}

interface SupporterItem {
    id: number;
    display_name: string;
    badge_label: string;
    total_amount_label: string;
    last_supported_at: string | null;
}

interface PaginatedSupporters {
    data: SupporterItem[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: {
        prev: string | null;
        next: string | null;
    };
}

interface SupportersPagePayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    footer: {
        copyright_text: string;
        icp_text: string | null;
        links: NavigationItem[];
        social_links: NavigationItem[];
    };
    summary: {
        total_supporters: number;
        total_amount_label: string;
    };
    supporters: PaginatedSupporters;
}

defineProps<{
    supportersPage: SupportersPagePayload;
}>();
</script>

<template>
    <SeoHead :seo="supportersPage.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="supportersPage.navigation"
            :site="supportersPage.site"
        />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-12 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Supporters
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">支持者墙</h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        感谢愿意公开展示昵称的支持者。这里不展示邮箱、订单号和任何支付明细。
                    </p>
                </div>

                <div class="rounded-sm bg-[#fffffe] px-5 py-4 shadow-sm">
                    <p class="text-sm text-[#5f6c7b]">公开支持者</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ supportersPage.summary.total_supporters }}
                    </p>
                    <p class="mt-1 text-sm text-[#5f6c7b]">
                        累计 {{ supportersPage.summary.total_amount_label }}
                    </p>
                </div>
            </div>
        </section>

        <section class="px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <div
                    v-if="supportersPage.supporters.data.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <article
                        v-for="supporter in supportersPage.supporters.data"
                        :key="supporter.id"
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-sm bg-[#3da9fc] text-[#fffffe]"
                            >
                                <Medal class="h-5 w-5" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold">
                                    {{ supporter.display_name }}
                                </h2>
                                <p class="mt-1 text-sm text-[#5f6c7b]">
                                    {{ supporter.badge_label }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="mt-5 rounded-sm bg-[#f7fbff] p-3 text-sm text-[#5f6c7b]"
                        >
                            <p>累计支持 {{ supporter.total_amount_label }}</p>
                            <p class="mt-1">
                                最近支持
                                {{
                                    supporter.last_supported_at ?? '时间待补充'
                                }}
                            </p>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-6 text-sm text-[#5f6c7b]"
                >
                    暂无公开支持者。
                </div>

                <div class="flex justify-between gap-3 text-sm font-semibold">
                    <Link
                        v-if="supportersPage.supporters.links.prev"
                        :href="supportersPage.supporters.links.prev"
                        class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        上一页
                    </Link>
                    <span v-else />
                    <Link
                        v-if="supportersPage.supporters.links.next"
                        :href="supportersPage.supporters.links.next"
                        class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    >
                        下一页
                    </Link>
                </div>
            </div>
        </section>

        <PublicFooter
            :footer="supportersPage.footer"
            :site="supportersPage.site"
        />
    </main>
</template>
