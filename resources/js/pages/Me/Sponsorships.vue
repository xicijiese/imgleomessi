<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { HeartHandshake } from 'lucide-vue-next';

interface NavigationItem {
    label: string;
    url: string;
}

interface SummaryPayload {
    favorites_count: number;
    comments_count: number;
    corrections_count: number;
    reports_count: number;
    pending_count: number;
    unread_notifications_count: number;
}

interface UserPayload {
    name: string;
    email: string;
    status_label: string;
    is_banned: boolean;
    banned_until: string | null;
    ban_reason: string | null;
}

interface SupporterProfilePayload {
    display_name: string | null;
    resolved_display_name: string;
    show_publicly: boolean;
    badge_label: string;
    total_amount_label: string;
    last_supported_at: string | null;
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

interface PaginatedOrders {
    data: OrderItem[];
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

interface MePayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    user: UserPayload;
    summary: SummaryPayload;
    supporter_profile: SupporterProfilePayload | null;
    orders: PaginatedOrders;
}

const props = defineProps<{
    me: MePayload;
}>();

const form = useForm({
    display_name: props.me.supporter_profile?.display_name ?? '',
    show_publicly: props.me.supporter_profile?.show_publicly ?? true,
});

const saveProfile = () => {
    form.patch('/me/sponsorships/profile', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="我的赞助" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="me.navigation" :site="me.site" />

        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Support</p>
                <h1 class="text-4xl font-semibold sm:text-5xl">我的赞助</h1>
                <p
                    class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                >
                    查看自己的赞助订单、支持者身份和公开展示偏好。
                </p>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[280px_minmax(0,1fr)]"
            >
                <UserCenterNav
                    :user="me.user"
                    :summary="me.summary"
                    active="sponsorships"
                />

                <div class="space-y-6">
                    <section
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <HeartHandshake
                                class="h-7 w-7 text-[#3da9fc]"
                                aria-hidden="true"
                            />
                            <div>
                                <h2 class="text-xl font-semibold">
                                    支持者资料
                                </h2>
                                <p class="mt-1 text-sm text-[#5f6c7b]">
                                    {{
                                        me.supporter_profile
                                            ? `当前身份：${me.supporter_profile.badge_label}，累计 ${me.supporter_profile.total_amount_label}`
                                            : '完成一次赞助后会生成支持者资料。'
                                    }}
                                </p>
                            </div>
                        </div>

                        <form
                            v-if="me.supporter_profile"
                            class="mt-6 grid gap-4"
                            @submit.prevent="saveProfile"
                        >
                            <label class="grid gap-2 text-sm font-semibold">
                                <span>公开昵称</span>
                                <input
                                    v-model="form.display_name"
                                    type="text"
                                    class="h-11 rounded-sm border border-[#90b4ce]/50 px-3 text-sm outline-none focus:border-[#3da9fc]"
                                    :placeholder="
                                        me.supporter_profile
                                            .resolved_display_name
                                    "
                                />
                            </label>

                            <label
                                class="flex items-center gap-3 text-sm font-semibold text-[#094067]"
                            >
                                <input
                                    v-model="form.show_publicly"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-[#90b4ce] text-[#3da9fc]"
                                />
                                出现在支持者墙
                            </label>

                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex h-10 items-center justify-center rounded-sm bg-[#094067] px-4 text-sm font-semibold text-[#fffffe] transition hover:bg-[#3da9fc] disabled:cursor-wait disabled:opacity-70"
                                    :disabled="form.processing"
                                >
                                    {{
                                        form.processing ? '保存中' : '保存设置'
                                    }}
                                </button>
                                <span
                                    v-if="form.recentlySuccessful"
                                    class="text-sm font-semibold text-[#3da9fc]"
                                    >已保存</span
                                >
                            </div>
                        </form>

                        <Link
                            v-else
                            href="/support"
                            class="mt-5 inline-flex h-10 items-center justify-center rounded-sm bg-[#094067] px-4 text-sm font-semibold text-[#fffffe] transition hover:bg-[#3da9fc]"
                        >
                            去支持本站
                        </Link>
                    </section>

                    <section
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <h2 class="text-xl font-semibold">赞助订单</h2>
                            <Link
                                href="/support"
                                class="text-sm font-semibold text-[#3da9fc]"
                                >继续支持</Link
                            >
                        </div>

                        <div
                            v-if="me.orders.data.length"
                            class="overflow-hidden rounded-sm border border-[#90b4ce]/35"
                        >
                            <table
                                class="min-w-full divide-y divide-[#90b4ce]/25 text-sm"
                            >
                                <thead
                                    class="bg-[#f7fbff] text-left text-[#5f6c7b]"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">
                                            订单号
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            方案
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            金额
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            状态
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            时间
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#90b4ce]/25">
                                    <tr
                                        v-for="order in me.orders.data"
                                        :key="order.id"
                                    >
                                        <td class="px-4 py-3 font-mono text-xs">
                                            {{ order.order_no }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ order.plan_name }}
                                        </td>
                                        <td class="px-4 py-3 font-semibold">
                                            {{ order.amount_label }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ order.status_label }}
                                        </td>
                                        <td class="px-4 py-3 text-[#5f6c7b]">
                                            {{
                                                order.paid_at ??
                                                order.created_at
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Link
                                                v-if="order.can_pay"
                                                :href="`/support/result?order=${order.order_no}`"
                                                class="font-semibold text-[#3da9fc]"
                                                >去支付</Link
                                            >
                                            <span v-else class="text-[#5f6c7b]"
                                                >-</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p
                            v-else
                            class="rounded-sm bg-[#f7fbff] p-4 text-sm text-[#5f6c7b]"
                        >
                            还没有赞助订单。
                        </p>
                    </section>
                </div>
            </div>
        </section>
    </main>
</template>
