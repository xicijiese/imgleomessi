<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Medal } from 'lucide-vue-next';

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

interface ContributorItem {
    id: number;
    display_name: string;
    bio: string | null;
    contribution_focus: string | null;
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
        total_contributors: number;
    };
    supporters: PaginatedSupporters;
    contributors: {
        data: ContributorItem[];
    };
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
                    <h1 class="text-4xl font-semibold sm:text-5xl">致谢墙</h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        感谢愿意公开展示昵称的运营守护者与档案共建者。这里不展示邮箱、订单号和任何支付明细。
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[26rem]">
                    <div class="rounded-sm bg-[#fffffe] px-5 py-4 shadow-sm">
                        <p class="text-sm text-[#5f6c7b]">运营守护者</p>
                        <p class="mt-1 text-3xl font-semibold">
                            {{ supportersPage.summary.total_supporters }}
                        </p>
                        <p class="mt-1 text-sm text-[#5f6c7b]">
                            累计 {{ supportersPage.summary.total_amount_label }}
                        </p>
                    </div>
                    <div class="rounded-sm bg-[#fffffe] px-5 py-4 shadow-sm">
                        <p class="text-sm text-[#5f6c7b]">档案共建者</p>
                        <p class="mt-1 text-3xl font-semibold">
                            {{ supportersPage.summary.total_contributors }}
                        </p>
                        <p class="mt-1 text-sm text-[#5f6c7b]">
                            共同整理影像资料
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold">运营守护者</h2>
                        <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">
                            感谢每一位通过赞助帮助档案库承担运营和维护成本的人。
                        </p>
                    </div>
                </div>

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
                    暂无公开展示的运营守护者。
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

                <section
                    aria-labelledby="contributors-heading"
                    class="border-t border-[#90b4ce]/30 pt-10"
                >
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <h2 id="contributors-heading" class="text-2xl font-semibold">
                                档案共建者
                            </h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#5f6c7b]">
                                他们用时间和资料整理能力，共同完善梅西影像档案。
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="supportersPage.contributors.data.length"
                        class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <article
                            v-for="contributor in supportersPage.contributors.data"
                            :key="contributor.id"
                            class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-sm bg-[#d8eefe] text-[#094067]"
                                >
                                    <BookOpen class="h-5 w-5" aria-hidden="true" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-semibold">
                                        {{ contributor.display_name }}
                                    </h3>
                                    <p
                                        v-if="contributor.contribution_focus"
                                        class="mt-1 text-sm text-[#3da9fc]"
                                    >
                                        {{ contributor.contribution_focus }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="contributor.bio"
                                class="mt-5 text-sm leading-7 text-[#5f6c7b]"
                            >
                                {{ contributor.bio }}
                            </p>
                        </article>
                    </div>

                    <div
                        v-else
                        class="mt-5 rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-6 text-sm leading-7 text-[#5f6c7b]"
                    >
                        目前还没有公开展示的档案共建者。名单会在管理员确认并开启公开展示后更新。
                    </div>
                </section>
            </div>
        </section>

        <PublicFooter
            :footer="supportersPage.footer"
            :site="supportersPage.site"
        />
    </main>
</template>
