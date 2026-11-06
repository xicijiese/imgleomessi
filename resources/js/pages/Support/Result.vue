<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';

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
const refundLabels: Record<string, string> = {
    completed: '已退款',
    processing: '渠道处理中',
    unknown: '待确认，请勿重复申请',
    failed: '退款失败，请联系管理员',
};

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

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <Head title="赞助结果" />
    <main ref="pageRoot" class="archive-browser archive-support">
        <PublicHeader
            :site="supportResult.site"
            :navigation="supportResult.navigation"
            theme="afa"
        />
        <div class="support-main">
            <div class="result">
                <section class="intro browser-heading">
                    <h1>赞助订单</h1>
                </section>
                <template v-if="supportResult.order"
                    ><section class="state-heading" role="status">
                        <h2>{{ supportResult.order.status_label }}</h2>
                        <p>
                            {{
                                supportResult.order.status === 'paid'
                                    ? '感谢你的支持，订单已入账。一次性支持仅保留历史记录。'
                                    : '支付结果以渠道核对为准，返回本页不代表已经支付。'
                            }}
                        </p>
                    </section>
                    <p
                        v-if="supportResult.order.payment_issue"
                        class="notice"
                        role="status"
                    >
                        {{ supportResult.order.payment_issue }}
                    </p>
                    <p v-if="supportResult.order.refund_state">
                        退款进度：{{
                            refundLabels[supportResult.order.refund_state] ??
                            '待核查'
                        }}
                    </p>
                    <div class="result-layout">
                        <div v-if="supportResult.order.status === 'pending'">
                            <p v-if="wechat" class="notice">
                                请点击微信右上角菜单，在系统浏览器打开本页完成支付。
                            </p>
                            <p v-else-if="expired" class="notice">
                                支付入口已过期。已支付请查询结果，未支付请返回方案页重新发起；旧订单仍接收支付通知。
                            </p>
                            <template v-else
                                ><a
                                    v-if="
                                        mobile &&
                                        supportResult.order.payment_url
                                    "
                                    :href="supportResult.order.payment_url"
                                    rel="noreferrer"
                                    class="action gold"
                                    >前往微信支付</a
                                >
                                <div v-else-if="supportResult.order.qr_url">
                                    <img
                                        :src="supportResult.order.qr_url"
                                        alt="微信支付二维码"
                                        referrerpolicy="no-referrer"
                                        class="payment-qr"
                                    />
                                    <p class="payment-copy">
                                        请使用微信扫一扫；二维码约5分钟有效。请勿重复付款。
                                    </p>
                                </div></template
                            >
                        </div>
                        <dl class="details">
                            <div>
                                <dt>订单号</dt>
                                <dd>{{ supportResult.order.order_no }}</dd>
                            </div>
                            <div>
                                <dt>方案</dt>
                                <dd>{{ supportResult.order.plan_name }}</dd>
                            </div>
                            <div>
                                <dt>金额</dt>
                                <dd>{{ supportResult.order.amount_label }}</dd>
                            </div>
                            <div>
                                <dt>支付方式</dt>
                                <dd>{{ supportResult.order.channel_label }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="result-actions">
                        <button
                            v-if="
                                supportResult.order.status === 'pending' &&
                                supportResult.order.channel === 'xunhu'
                            "
                            type="button"
                            class="action gold"
                            :disabled="processing"
                            @click="checkPayment"
                        >
                            {{
                                processing ? '正在核对' : '我已支付，查询结果'
                            }}</button
                        ><Link class="action" href="/me/sponsorships"
                            >查看我的赞助</Link
                        >
                    </div></template
                >
                <section v-else class="empty">
                    暂未找到赞助订单。<Link class="quiet-link" href="/support"
                        >返回支持本站</Link
                    >
                </section>
            </div>
        </div>
    </main>
</template>
<style scoped src="../../../css/support-pages.css"></style>
