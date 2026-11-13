<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}
interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
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
interface OptionItem {
    value: string;
    label: string;
}
interface NotificationItem {
    id: string;
    category: string;
    title: string;
    message: string;
    url: string | null;
    is_read: boolean;
    read_at: string | null;
    created_at: string | null;
}
interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: { prev: string | null; next: string | null };
}
interface MePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    user: UserPayload;
    summary: SummaryPayload;
    filters: { status: string; type: string };
    options: { statuses: OptionItem[]; types: OptionItem[] };
    notifications: Paginated<NotificationItem>;
}

const props = defineProps<{ me: MePayload }>();
const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
const isFirstEmpty = computed(
    () =>
        props.me.notifications.meta.total === 0 &&
        !props.me.filters.status &&
        !props.me.filters.type,
);
const notices = ref([...props.me.notifications.data]);
watch(
    () => props.me.notifications.data,
    (data) => {
        notices.value = [...data];
    },
);
const pending = ref<Record<string, boolean>>({});
const allPending = ref(false);
const status = ref<string | null>(null);
const form = reactive({
    status: props.me.filters.status,
    type: props.me.filters.type,
});

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const applyFilters = () =>
    router.get(
        '/me/notifications',
        { status: form.status || undefined, type: form.type || undefined },
        { preserveScroll: false, preserveState: false, replace: true },
    );
const resetFilters = () =>
    router.get(
        '/me/notifications',
        {},
        { preserveScroll: false, preserveState: false, replace: true },
    );
const patchJson = async (url: string) => {
    const response = await fetch(url, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    if (!response.ok) throw new Error('read_failed');
};
const clearStatus = () => window.setTimeout(() => (status.value = null), 1800);
const markRead = async (notice: NotificationItem) => {
    if (notice.is_read || pending.value[notice.id]) return;
    pending.value = { ...pending.value, [notice.id]: true };
    try {
        await patchJson('/me/notifications/' + notice.id + '/read');
        notices.value = notices.value.map((item) =>
            item.id === notice.id ? { ...item, is_read: true } : item,
        );
        status.value = '已标为已读。';
        router.reload({ only: ['me'] });
    } catch {
        status.value = '操作失败，请稍后重试。';
    } finally {
        pending.value = { ...pending.value, [notice.id]: false };
        clearStatus();
    }
};
const markAllRead = async () => {
    if (allPending.value) return;
    allPending.value = true;
    try {
        await patchJson('/me/notifications/read-all');
        notices.value = notices.value.map((item) => ({
            ...item,
            is_read: true,
        }));
        status.value = '已全部标为已读。';
        router.reload({ only: ['me'] });
    } catch {
        status.value = '操作失败，请稍后重试。';
    } finally {
        allPending.value = false;
        clearStatus();
    }
};
</script>

<template>
    <Head title="通知中心" />
    <div ref="root" class="notifications-page">
        <PublicHeader :navigation="me.navigation" :site="me.site" theme="afa" />
        <div class="notifications-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="notifications"
                variant="archive"
            />
            <main class="notices-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>通知中心</h1>
                        <p>查看评论审核、举报处理和账号状态通知。</p>
                    </div>
                    <div class="all-action">
                        <button
                            class="filter-clear"
                            type="button"
                            :disabled="allPending"
                            @click="markAllRead"
                        >
                            {{ allPending ? '处理中' : '全部标为已读' }}</button
                        ><span>作用于全部通知，不受当前筛选限制</span>
                    </div>
                </div>
                <form class="comment-filters" @submit.prevent="applyFilters">
                    <div class="comment-filter">
                        <label for="notice-status">阅读状态</label
                        ><select
                            id="notice-status"
                            v-model="form.status"
                            name="status"
                        >
                            <option value="">全部状态</option>
                            <option
                                v-for="item in me.options.statuses"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                    <div class="comment-filter">
                        <label for="notice-type">通知类型</label
                        ><select
                            id="notice-type"
                            v-model="form.type"
                            name="type"
                        >
                            <option value="">全部类型</option>
                            <option
                                v-for="item in me.options.types"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                    <div class="comment-filter-actions">
                        <button class="filter-primary" type="submit">
                            应用筛选</button
                        ><button
                            class="filter-clear"
                            type="button"
                            @click="resetFilters"
                        >
                            清空条件
                        </button>
                    </div>
                </form>
                <div class="record-toolbar">
                    <p>
                        共 {{ me.notifications.meta.total }} 条通知 · 第
                        {{ me.notifications.meta.current_page }} /
                        {{ me.notifications.meta.last_page }} 页
                    </p>
                    <p>按通知时间倒序</p>
                </div>
                <p
                    v-if="status"
                    id="feedback"
                    :role="status.startsWith('操作失败') ? 'alert' : 'status'"
                >
                    {{ status }}
                </p>
                <section
                    v-if="notices.length"
                    id="notices"
                    aria-label="通知列表"
                >
                    <article
                        v-for="notice in notices"
                        :key="notice.id"
                        class="notice"
                        :data-read="notice.is_read"
                    >
                        <div class="notice-main">
                            <div class="notice-meta">
                                <span class="read-label">{{
                                    notice.is_read ? '已读' : '未读'
                                }}</span
                                ><span>{{ notice.created_at }}</span>
                            </div>
                            <h2>{{ notice.title }}</h2>
                            <p class="notice-message">{{ notice.message }}</p>
                        </div>
                        <div class="notice-actions">
                            <Link
                                v-if="notice.url"
                                :href="
                                    '/me/notifications/' + notice.id + '/open'
                                "
                                method="post"
                                as="button"
                                type="button"
                                class="filter-clear"
                                >查看详情</Link
                            ><button
                                v-if="!notice.is_read"
                                class="filter-clear mark-read"
                                type="button"
                                :disabled="pending[notice.id]"
                                @click="markRead(notice)"
                            >
                                {{ pending[notice.id] ? '处理中' : '标为已读' }}
                            </button>
                        </div>
                    </article>
                </section>
                <div v-else class="records-empty">
                    <h2>
                        {{ isFirstEmpty ? '还没有通知' : '暂无符合条件的通知' }}
                    </h2>
                    <p>
                        {{
                            isFirstEmpty
                                ? '你的通知会显示在这里。'
                                : '可以调整阅读状态或通知类型，再查看。'
                        }}
                    </p>
                    <button
                        v-if="!isFirstEmpty"
                        class="filter-clear"
                        type="button"
                        @click="resetFilters"
                    >
                        清空筛选条件
                    </button>
                </div>
                <ArchivePagination
                    :meta="me.notifications.meta"
                    :links="me.notifications.links"
                    label="通知分页"
                />
            </main>
        </div>
    </div>
</template>
<style scoped src="./Notifications.css"></style>
