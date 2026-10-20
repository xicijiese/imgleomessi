<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock3, HeartHandshake } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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
    channel: string;
    status: string;
    status_label: string;
    can_pay: boolean;
    payment_url: string | null;
    qr_url: string | null;
    qr_expires_at: string | null;
    payment_issue: string | null;
    refund_state: string | null;
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

const wechat = ref(false);
const mobile = ref(false);
const ticks = ref(Date.now());
const expired = computed(
    () =>
        !props.supportResult.order?.qr_expires_at ||
        ticks.value >= Date.parse(props.supportResult.order.qr_expires_at),
);
let timer: ReturnType<typeof setInterval> | undefined;
const checkPayment = () => {
    if (!props.supportResult.order || processing.value) return;
    processing.value = true;
    router.post(
        `/support/orders/${props.supportResult.order.id}/check`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
onMounted(() => {
    wechat.value = /MicroMessenger/i.test(navigator.userAgent);
    mobile.value = /Android|iPhone|iPad|Mobile/i.test(navigator.userAgent);
    const started = Date.now();
    let seconds = 0;
    timer = setInterval(() => {
        ticks.value = Date.now();
        seconds++;
        if (
            seconds % 10 === 0 &&
            Date.now() - started < 600000 &&
            !document.hidden &&
            props.supportResult.order?.channel === 'xunhu' &&
            props.supportResult.order.status === 'pending' &&
            !props.supportResult.order.payment_issue
        )
            checkPayment();
    }, 1000);
});
onUnmounted(() => {
    if (timer) clearInterval(timer);
});
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
                                            ? '感谢你的支持，订单已入账。一次性支持仅保留历史记录。'
                                            : '支付结果以渠道核对为准，返回本页不代表已经支付。'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="supportResult.order.payment_issue"
                        role="status"
                        class="text-sm leading-6 text-[#5f6c7b]"
                    >
                        {{ supportResult.order.payment_issue }}
                    </p>
                    <p v-if="supportResult.order.refund_state" class="text-sm">
                        退款进度：{{
                            {
                                completed: '已退款',
                                processing: '渠道处理中',
                                unknown: '待确认，请勿重复申请',
                                failed: '退款失败，请联系管理员',
                            }[supportResult.order.refund_state] ?? '待核查'
                        }}
                    </p>
                    <div
                        v-if="supportResult.order.status === 'pending'"
                        class="space-y-4"
                    >
                        <p v-if="wechat" class="rounded-sm bg-[#d8eefe] p-4">
                            请点击微信右上角菜单，在系统浏览器打开本页完成支付。
                        </p>
                        <p v-else-if="expired" class="text-sm text-[#5f6c7b]">
                            支付入口已过期。已支付请查询结果，未支付请返回方案页重新发起；旧订单仍接收支付通知。
                        </p>
                        <template v-else>
                            <a
                                v-if="mobile && supportResult.order.payment_url"
                                :href="supportResult.order.payment_url"
                                rel="noreferrer"
                                class="inline-flex rounded-sm bg-[#094067] px-5 py-3 font-semibold text-white"
                                >前往微信支付</a
                            >
                            <div
                                v-else-if="supportResult.order.qr_url"
                                class="space-y-2"
                            >
                                <img
                                    :src="supportResult.order.qr_url"
                                    alt="微信支付二维码"
                                    referrerpolicy="no-referrer"
                                    class="h-56 w-56 object-contain"
                                />
                                <p class="text-sm text-[#5f6c7b]">
                                    请使用微信扫一扫；二维码约 5
                                    分钟有效。请勿重复付款。
                                </p>
                            </div>
                        </template>
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
                            v-if="
                                supportResult.order.status === 'pending' &&
                                supportResult.order.channel === 'xunhu'
                            "
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-sm bg-[#094067] px-5 text-sm font-semibold text-[#fffffe] transition hover:bg-[#3da9fc] disabled:cursor-wait disabled:opacity-70"
                            :disabled="processing"
                            @click="checkPayment"
                        >
                            {{ processing ? '正在核对' : '我已支付，查询结果' }}
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
    </main>
</template>
