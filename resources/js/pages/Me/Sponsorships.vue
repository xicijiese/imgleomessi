<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
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
    <div ref="root" class="sponsorship-page">
        <PublicHeader :navigation="me.navigation" :site="me.site" theme="afa" />
        <div class="sponsorship-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="sponsorships"
                variant="archive"
            />
            <main class="sponsor-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>我的赞助</h1>
                        <p>查看赞助订单、运营守护者身份与公开展示偏好。</p>
                    </div>
                </div>
                <section class="profile-panel" aria-labelledby="profile-title">
                    <div class="profile-summary">
                        <h2 id="profile-title">运营守护者资料</h2>
                        <template v-if="me.supporter_profile"
                            ><p class="identity-label">当前身份</p>
                            <strong class="badge-title">{{
                                me.supporter_profile.badge_label
                            }}</strong>
                            <p class="total-label">
                                累计赞助
                                <strong>{{
                                    me.supporter_profile.total_amount_label
                                }}</strong>
                            </p>
                            <p class="saved-public">
                                已保存：{{
                                    me.supporter_profile.show_publicly
                                        ? '出现在致谢墙'
                                        : '不出现在致谢墙'
                                }}
                            </p></template
                        >
                        <p v-else class="no-profile-copy">
                            完成一次赞助后会生成运营守护者资料。
                        </p>
                    </div>
                    <form
                        v-if="me.supporter_profile"
                        id="profile-form"
                        @submit.prevent="saveProfile"
                    >
                        <label for="display-name">公开昵称</label
                        ><input
                            id="display-name"
                            v-model="form.display_name"
                            name="display_name"
                            type="text"
                            maxlength="80"
                            :placeholder="
                                me.supporter_profile.resolved_display_name
                            "
                            :aria-invalid="!!form.errors.display_name"
                            :aria-describedby="
                                form.errors.display_name
                                    ? 'name-help name-error'
                                    : 'name-help'
                            "
                        />
                        <p id="name-help" class="help">
                            留空时使用账号昵称，最多80个字符。
                        </p>
                        <p
                            v-if="form.errors.display_name"
                            id="name-error"
                            role="alert"
                        >
                            {{ form.errors.display_name }}
                        </p>
                        <label class="privacy-option"
                            ><input
                                id="show-publicly"
                                v-model="form.show_publicly"
                                name="show_publicly"
                                type="checkbox"
                                :aria-invalid="!!form.errors.show_publicly"
                                :aria-describedby="
                                    form.errors.show_publicly
                                        ? 'visibility-error'
                                        : undefined
                                "
                            />出现在致谢墙</label
                        >
                        <p
                            v-if="form.errors.show_publicly"
                            id="visibility-error"
                            role="alert"
                        >
                            {{ form.errors.show_publicly }}
                        </p>
                        <p class="help">修改后点击保存设置生效。</p>
                        <div class="save-row">
                            <button
                                type="submit"
                                class="filter-primary"
                                :disabled="form.processing"
                            >
                                {{
                                    form.processing ? '保存中' : '保存设置'
                                }}</button
                            ><span v-if="form.recentlySuccessful" role="status"
                                >已保存</span
                            >
                        </div>
                    </form>
                    <div v-else class="no-profile-action">
                        <Link class="filter-primary" href="/support"
                            >去支持本站</Link
                        >
                    </div>
                </section>
                <section class="orders-section" aria-labelledby="orders-title">
                    <div class="orders-heading">
                        <div>
                            <h2 id="orders-title">赞助订单</h2>
                            <p>
                                共 {{ me.orders.meta.total }} 条订单 · 第
                                {{ me.orders.meta.current_page }} /
                                {{ me.orders.meta.last_page }} 页
                            </p>
                        </div>
                        <Link class="secondary" href="/support">继续支持</Link>
                    </div>
                    <table v-if="me.orders.data.length">
                        <thead>
                            <tr>
                                <th scope="col">订单号</th>
                                <th scope="col">方案</th>
                                <th scope="col">金额</th>
                                <th scope="col">状态</th>
                                <th scope="col">时间</th>
                                <th scope="col">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in me.orders.data" :key="order.id">
                                <td data-label="订单号" class="order-no">
                                    {{ order.order_no }}
                                </td>
                                <td data-label="方案" class="plan">
                                    {{ order.plan_name }}
                                </td>
                                <td data-label="金额" class="amount">
                                    {{ order.amount_label }}
                                </td>
                                <td data-label="状态">
                                    <span
                                        class="order-status"
                                        :data-status="order.status"
                                        >{{ order.status_label }}</span
                                    >
                                </td>
                                <td data-label="时间" class="date">
                                    {{ order.paid_at ?? order.created_at }}
                                </td>
                                <td data-label="操作">
                                    <Link
                                        v-if="order.can_pay"
                                        class="pay-link"
                                        :href="`/support/result?order=${order.order_no}`"
                                        >去支付</Link
                                    ><span v-else class="no-action">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="empty-orders">
                        <h3>还没有赞助订单</h3>
                        <p>你的赞助订单会显示在这里。</p>
                    </div>
                    <ArchivePagination
                        :meta="me.orders.meta"
                        :links="me.orders.links"
                        label="赞助订单分页"
                    />
                </section>
            </main>
        </div>
    </div>
</template>
<style scoped src="./Sponsorships.css"></style>
