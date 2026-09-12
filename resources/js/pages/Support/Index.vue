<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Check,
    HeartHandshake,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface PlanItem {
    id: number;
    name: string;
    amount_label: string;
    duration_label: string;
    badge_label: string;
    benefits: string[];
}

interface SupportPayload {
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
    plans: PlanItem[];
    auth: {
        can_support: boolean;
        is_supporter: boolean;
        supporter_until: string | null;
    };
}

const props = defineProps<{
    support: SupportPayload;
}>();

const processingPlanId = ref<number | null>(null);

const createOrder = (plan: PlanItem) => {
    processingPlanId.value = plan.id;
    router.post(
        '/support/orders',
        { plan_id: plan.id },
        {
            preserveScroll: false,
            onFinish: () => {
                processingPlanId.value = null;
            },
        },
    );
};
</script>

<template>
    <SeoHead :seo="support.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="support.navigation" :site="support.site" />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-12 sm:px-6 lg:px-8"
        >
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end"
            >
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">
                        Support
                    </p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">
                        支持本站维护
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                    >
                        赞助只用于支持资料整理、服务器、存储和后续功能维护，不代表购买图片版权，也不会解锁原图下载权益。
                    </p>
                    <p
                        class="mt-3 max-w-2xl text-sm font-semibold leading-7 text-[#3d7aa3] sm:text-base"
                    >
                        运营成本之外的结余，将用于捐赠给公益机构；相关捐赠信息会在条件允许时公开。
                    </p>
                </div>

                <div
                    class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <HeartHandshake
                            class="h-7 w-7 text-[#3da9fc]"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="font-semibold">P1 模拟支付阶段</p>
                            <p class="mt-1 text-sm text-[#5f6c7b]">
                                本阶段不接真实微信/支付宝接口。
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="support.auth.is_supporter"
                        class="mt-4 rounded-sm bg-[#d8eefe] p-3 text-sm font-semibold text-[#094067]"
                    >
                        你已是运营守护者<span v-if="support.auth.supporter_until"
                            >，有效期至 {{ support.auth.supporter_until }}</span
                        >
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-[#90b4ce]/30 px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-3xl">
                    <h2 class="text-2xl font-semibold sm:text-3xl">
                        参与档案库的两种方式
                    </h2>
                    <p class="mt-3 text-sm leading-7 text-[#5f6c7b] sm:text-base">
                        影像档案的长期整理，既需要运营成本，也需要愿意投入时间和资料的同行者。你可以按自己的方式参与，共同让这些记忆更完整。
                    </p>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <article
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-6"
                    >
                        <HeartHandshake
                            class="h-7 w-7 text-[#3da9fc]"
                            aria-hidden="true"
                        />
                        <h3 class="mt-4 text-xl font-semibold">运营守护者</h3>
                        <p class="mt-3 text-sm leading-7 text-[#5f6c7b]">
                            通过赞助分担服务器、对象存储、图片处理和日常维护成本。运营守护者不代表购买图片版权，也不会获得原图下载或转载授权。
                        </p>
                    </article>

                    <article
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-6"
                    >
                        <BookOpen
                            class="h-7 w-7 text-[#3da9fc]"
                            aria-hidden="true"
                        />
                        <h3 class="mt-4 text-xl font-semibold">档案共建者</h3>
                        <p class="mt-3 text-sm leading-7 text-[#5f6c7b]">
                            用图片库存、资料考据和整理能力，参与图片、相册、分类、标签与来源信息的维护。档案共建者由管理员在私域确认后邀请加入，不开放公开申请。
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-8">
                <div class="grid gap-4 lg:grid-cols-3">
                    <article
                        v-for="plan in support.plans"
                        :key="plan.id"
                        class="flex min-h-[360px] flex-col rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-6 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-2xl font-semibold">
                                    {{ plan.name }}
                                </h2>
                                <p class="mt-2 text-sm text-[#5f6c7b]">
                                    {{ plan.duration_label }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-[#d8eefe] px-3 py-1 text-xs font-semibold text-[#094067]"
                                >{{ plan.badge_label }}</span
                            >
                        </div>

                        <p class="mt-7 text-4xl font-semibold">
                            {{ plan.amount_label }}
                        </p>

                        <ul
                            class="mt-6 grid gap-3 text-sm leading-6 text-[#5f6c7b]"
                        >
                            <li
                                v-for="benefit in plan.benefits"
                                :key="benefit"
                                class="flex gap-2"
                            >
                                <Check
                                    class="mt-1 h-4 w-4 shrink-0 text-[#3da9fc]"
                                    aria-hidden="true"
                                />
                                <span>{{ benefit }}</span>
                            </li>
                        </ul>

                        <button
                            v-if="support.auth.can_support"
                            type="button"
                            class="mt-auto inline-flex h-11 items-center justify-center rounded-sm bg-[#094067] px-5 text-sm font-semibold text-[#fffffe] transition hover:bg-[#3da9fc] disabled:cursor-wait disabled:opacity-70"
                            :disabled="processingPlanId !== null"
                            @click="createOrder(plan)"
                        >
                            {{
                                processingPlanId === plan.id
                                    ? '创建订单中'
                                    : '选择支持'
                            }}
                        </button>
                        <Link
                            v-else
                            href="/login"
                            class="mt-auto inline-flex h-11 items-center justify-center rounded-sm bg-[#094067] px-5 text-sm font-semibold text-[#fffffe] transition hover:bg-[#3da9fc]"
                        >
                            登录后支持
                        </Link>
                    </article>
                </div>

                <section class="grid gap-4 lg:grid-cols-3">
                    <div
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-5"
                    >
                        <ShieldCheck
                            class="h-6 w-6 text-[#3da9fc]"
                            aria-hidden="true"
                        />
                        <h2 class="mt-4 text-lg font-semibold">不售卖版权</h2>
                        <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">
                            赞助不会赋予图片下载、转载、商用或授权使用权。
                        </p>
                    </div>
                    <div
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-5"
                    >
                        <Sparkles
                            class="h-6 w-6 text-[#3da9fc]"
                            aria-hidden="true"
                        />
                        <h2 class="mt-4 text-lg font-semibold">
                            身份展示可关闭
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">
                            运营守护者可在个人中心决定是否出现在致谢墙。
                        </p>
                    </div>
                    <Link
                        href="/supporters"
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#f7fbff] p-5 transition hover:border-[#3da9fc]"
                    >
                        <Users
                            class="h-6 w-6 text-[#3da9fc]"
                            aria-hidden="true"
                        />
                        <h2 class="mt-4 text-lg font-semibold">查看致谢墙</h2>
                        <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">
                            这里只展示愿意公开昵称的运营守护者和档案共建者。
                        </p>
                    </Link>
                </section>
            </div>
        </section>

        <PublicFooter :footer="support.footer" :site="support.site" />
    </main>
</template>
