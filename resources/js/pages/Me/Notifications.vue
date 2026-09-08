<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCheck } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface NavigationItem { label: string; url: string; }
interface SitePayload { name: string; logo_url: string | null; search_placeholder: string; }
interface SummaryPayload { favorites_count: number; comments_count: number; corrections_count: number; reports_count: number; pending_count: number; unread_notifications_count: number; }
interface UserPayload { name: string; email: string; status_label: string; is_banned: boolean; banned_until: string | null; ban_reason: string | null; }
interface OptionItem { value: string; label: string; }
interface NotificationItem { id: string; category: string; title: string; message: string; url: string | null; is_read: boolean; read_at: string | null; created_at: string | null; }
interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null; }; links: { prev: string | null; next: string | null; }; }
interface MePayload { site: SitePayload; navigation: NavigationItem[]; user: UserPayload; summary: SummaryPayload; filters: { status: string; type: string; }; options: { statuses: OptionItem[]; types: OptionItem[]; }; notifications: Paginated<NotificationItem>; }

const props = defineProps<{ me: MePayload }>();
const notices = ref([...props.me.notifications.data]);
const pending = ref<Record<string, boolean>>({});
const allPending = ref(false);
const status = ref<string | null>(null);
const form = reactive({ status: props.me.filters.status, type: props.me.filters.type });

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const applyFilters = () => router.get('/me/notifications', { status: form.status || undefined, type: form.type || undefined }, { preserveScroll: false, preserveState: false, replace: true });
const resetFilters = () => router.get('/me/notifications', {}, { preserveScroll: false, preserveState: false, replace: true });
const patchJson = async (url: string) => {
    const response = await fetch(url, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error('read_failed');
};
const clearStatus = () => window.setTimeout(() => (status.value = null), 1800);
const markRead = async (notice: NotificationItem) => {
    if (notice.is_read || pending.value[notice.id]) return;
    pending.value = { ...pending.value, [notice.id]: true };
    try {
        await patchJson('/me/notifications/' + notice.id + '/read');
        notices.value = notices.value.map((item) => item.id === notice.id ? { ...item, is_read: true } : item);
        status.value = '已标为已读。';
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
        notices.value = notices.value.map((item) => ({ ...item, is_read: true }));
        status.value = '已全部标为已读。';
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
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="me.navigation" :site="me.site" />
        <section class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Notifications</p>
                    <h1 class="text-4xl font-semibold sm:text-5xl">通知中心</h1>
                    <p class="mt-4 text-sm text-[#5f6c7b]">评论审核、举报处理和账号状态通知都会出现在这里。</p>
                </div>
                <button type="button" class="inline-flex items-center gap-2 rounded-sm bg-[#3da9fc] px-4 py-2 text-sm font-semibold text-[#fffffe]" :disabled="allPending" @click="markAllRead">
                    <CheckCheck class="h-4 w-4" aria-hidden="true" />
                    {{ allPending ? '处理中' : '全部已读' }}
                </button>
            </div>
        </section>
        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <UserCenterNav :user="me.user" :summary="me.summary" active="notifications" />
                <div class="space-y-6">
                    <form class="flex flex-wrap gap-3 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm" @submit.prevent="applyFilters">
                        <select v-model="form.status" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm">
                            <option value="">全部状态</option>
                            <option v-for="item in me.options.statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                        <select v-model="form.type" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm">
                            <option value="">全部类型</option>
                            <option v-for="item in me.options.types" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                        <button class="rounded-sm bg-[#3da9fc] px-4 py-2 text-sm font-semibold text-[#fffffe]" type="submit">筛选</button>
                        <button class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold" type="button" @click="resetFilters">清空</button>
                    </form>
                    <p v-if="status" class="rounded-sm bg-[#d8eefe] px-4 py-3 text-sm font-semibold">{{ status }}</p>
                    <div v-if="notices.length" class="space-y-3">
                        <article v-for="notice in notices" :key="notice.id" class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-lg font-semibold">{{ notice.title }}</h2>
                                        <span v-if="!notice.is_read" class="rounded-full bg-[#ef4565] px-2 py-0.5 text-xs font-semibold text-[#fffffe]">未读</span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">{{ notice.message }}</p>
                                    <p class="mt-2 text-xs text-[#5f6c7b]">{{ notice.created_at }}</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <Link v-if="notice.url" :href="notice.url" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm font-semibold">查看</Link>
                                    <button v-if="!notice.is_read" type="button" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm font-semibold hover:border-[#3da9fc]" :disabled="pending[notice.id]" @click="markRead(notice)">
                                        {{ pending[notice.id] ? '处理中' : '标为已读' }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                    <p v-else class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-6 text-sm text-[#5f6c7b]">暂无符合条件的通知。</p>
                    <div class="flex justify-between gap-3">
                        <Link v-if="me.notifications.links.prev" :href="me.notifications.links.prev" class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold">上一页</Link><span v-else></span>
                        <Link v-if="me.notifications.links.next" :href="me.notifications.links.next" class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold">下一页</Link>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
