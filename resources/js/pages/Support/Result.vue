<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock3, HeartHandshake } from 'lucide-vue-next';
import { ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface OrderItem {
    id: number;
    order_no: string;
    plan_name: string;
    amount_label: string;
    channel_label: string;
    status: string;
    status_label: string;
    can_pay: boolean;
    created_at: string | null;
    paid_at: string | null;
}

interface SupportResultPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    footer: {
        copyright_text: string;
        icp_text: string | null;
        links: NavigationItem[];
        social_links: NavigationItem[];
    };
    order: OrderItem | null;
}

const props = defineProps<{
    supportResult: SupportResultPayload;
}>();

const processing = ref(false);

const mockPay = () => {
    if (!props.supportResult.order) {
        return;
    }

    processing.value = true;
    router.post(
        `/support/orders/${props.supportResult.order.id}/mock-pay`,
        {},
        {
            preserveScroll: false,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="赞助结果" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :navigation="supportResult.navigation"
            :site="supportResult.site"
        />

        <section class="px-4 py-12 sm:px-6 lg:px-8">
            <div
                class="mx-auto max-w-3xl rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-6 shadow-sm sm:p-8"
            >
                <div class="flex items-center gap-3">
                    <HeartHandshake
                        class="h-8 w-8 text-[#3da9fc]"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-sm font-semibold text-[#3da9fc]">
                            Support Result
                        </p>
                        <h1 class="text-3xl font-semibold">赞助订单</h1>
                    </div>
                </div>

                <div v-if="supportResult.order" class="mt-8 space-y-5">
                    <div class="rounded-sm bg-[#d8eefe] p-5">
                        <div class="flex items-start gap-3">
                            <CheckCircle2
                                v-if="supportResult.order.status === 'paid'"
                                class="h-6 w-6 shrink-0 text-[#3da9fc]"
                                aria-hidden="true"
                            />
                            <Clock3
                                v-else
                                class="h-6 w-6 shrink-0 text-[#094067]"
                                aria-hidden="true"
                            />
                            <div>
                                <p class="font-semibold">
                                    {{ supportResult.order.status_label }}
                                </p>
                                <p class="mt-1 text-sm text-[#5f6c7b]">
                                    {{
                                        supportResult.order.status === 'paid'
                                            ? '感谢你的支持，运营守护者身份已经更新。'
                                            : 'P1 阶段使用模拟支付按钮完成流程验收。'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-sm border border-[#90b4ce]/30 p-4">
                            <dt class="text-[#5f6c7b]">订单号</dt>
                            <dd class="mt-1 font-semibold">
                                {{ supportResult.order.order_no }}
                            </dd>
                        </div>
                        <div class="rounded-sm border border-[#90b4ce]/30 p-4">
                            <dt class="text-[#5f6c7b]">方案</dt>
                            <dd class="mt-1 font-semibold">
                                {{ supportResult.order.plan_name }}
                            </dd>
                        </div>
                        <div class="rounded-sm border border-[#90b4ce]/30 p-4">
                            <dt class="text-[#5f6c7b]">金额</dt>
                            <dd class="mt-1 font-semibold">
                                {{ supportResult.order.amount_label }}
                            </dd>
                        </div>
                        <div class="rounded-sm border border-[#90b4ce]/30 p-4">
                            <dt class="text-[#5f6c7b]">支付方式</dt>
                            <dd class="mt-1 font-semibold">
                                {{ supportResult.order.channel_label }}
                            </dd>
                        </div>
                    </dl>

                    <div class="flex flex-wrap gap-3">
                        <button
                            v-if="supportResult.order.can_pay"
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-sm bg-[#094067] px-5 text-sm font-semibold text-[#fffffe] transition hover:bg-[#3da9fc] disabled:cursor-wait disabled:opacity-70"
                            :disabled="processing"
                            @click="mockPay"
                        >
                            {{ processing ? '模拟支付中' : '模拟支付成功' }}
                        </button>
                        <Link
                            href="/me/sponsorships"
                            class="inline-flex h-11 items-center justify-center rounded-sm border border-[#90b4ce]/60 px-5 text-sm font-semibold text-[#094067] transition hover:border-[#3da9fc] hover:text-[#3da9fc]"
                        >
                            查看我的赞助
                        </Link>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-8 rounded-sm bg-[#f7fbff] p-5 text-sm leading-6 text-[#5f6c7b]"
                >
                    暂未找到赞助订单。
                    <Link href="/support" class="font-semibold text-[#3da9fc]"
                        >返回支持本站</Link
                    >
                </div>
            </div>
        </section>

        <PublicFooter
            :footer="supportResult.footer"
            :site="supportResult.site"
        />
    </main>
</template>
