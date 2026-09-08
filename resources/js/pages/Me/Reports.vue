<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff } from 'lucide-vue-next';
import { reactive } from 'vue';

interface NavigationItem { label: string; url: string; }
interface SitePayload { name: string; logo_url: string | null; search_placeholder: string; }
interface SummaryPayload { favorites_count: number; comments_count: number; corrections_count: number; reports_count: number; pending_count: number; unread_notifications_count: number; }
interface UserPayload { name: string; email: string; status_label: string; is_banned: boolean; banned_until: string | null; ban_reason: string | null; }
interface OptionItem { value: string; label: string; }
interface PhotoSummary { is_available: boolean; title: string; url: string | null; image_url: string | null; category_summary: string | null; }
interface ReportItem { id: number; reason_label: string; status: string; status_label: string; details: string | null; result_message: string; created_at: string | null; handled_at: string | null; target: { type_label: string; comment_excerpt: string; comment_user_name: string | null; photo: PhotoSummary; }; }
interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null; }; links: { prev: string | null; next: string | null; }; }
interface MePayload { site: SitePayload; navigation: NavigationItem[]; user: UserPayload; summary: SummaryPayload; filters: { status: string; }; options: { statuses: OptionItem[]; reasons: OptionItem[]; }; reports: Paginated<ReportItem>; }

const props = defineProps<{ me: MePayload }>();
const form = reactive({ status: props.me.filters.status });
const applyFilters = () => router.get('/me/reports', { status: form.status || undefined }, { preserveScroll: false, preserveState: false, replace: true });
const resetFilters = () => router.get('/me/reports', {}, { preserveScroll: false, preserveState: false, replace: true });
</script>

<template>
    <Head title="我的举报" />
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="me.navigation" :site="me.site" />
        <section class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Reports</p>
                <h1 class="text-4xl font-semibold sm:text-5xl">我的举报</h1>
                <p class="mt-4 text-sm text-[#5f6c7b]">查看自己提交过的举报和处理状态。</p>
            </div>
        </section>
        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <UserCenterNav :user="me.user" :summary="me.summary" active="reports" />
                <div class="space-y-6">
                    <form class="flex flex-wrap gap-3 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm" @submit.prevent="applyFilters">
                        <select v-model="form.status" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm">
                            <option value="">全部状态</option>
                            <option v-for="item in me.options.statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                        <button class="rounded-sm bg-[#3da9fc] px-4 py-2 text-sm font-semibold text-[#fffffe]" type="submit">筛选</button>
                        <button class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold" type="button" @click="resetFilters">清空</button>
                    </form>
                    <div v-if="me.reports.data.length" class="space-y-4">
                        <article v-for="report in me.reports.data" :key="report.id" class="grid gap-4 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm md:grid-cols-[160px_minmax(0,1fr)]">
                            <Link :href="report.target.photo.url ?? '/me/reports'" class="block overflow-hidden rounded-sm bg-[#d8eefe]">
                                <img v-if="report.target.photo.image_url" :src="report.target.photo.image_url" :alt="report.target.photo.title" class="aspect-[4/3] w-full object-cover" />
                                <div v-else class="flex aspect-[4/3] items-center justify-center text-[#5f6c7b]"><ImageOff class="h-6 w-6" /></div>
                            </Link>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                                    <span class="rounded-full bg-[#d8eefe] px-2 py-1">{{ report.reason_label }}</span>
                                    <span class="rounded-full bg-[#f7fbff] px-2 py-1 text-[#5f6c7b]">{{ report.status_label }}</span>
                                </div>
                                <h2 class="mt-3 line-clamp-1 font-semibold">{{ report.target.photo.title }}</h2>
                                <p class="mt-2 text-sm leading-6 text-[#5f6c7b]">目标评论：{{ report.target.comment_excerpt || '内容暂不可见' }}</p>
                                <p v-if="report.details" class="mt-2 text-sm leading-6">举报补充：{{ report.details }}</p>
                                <p class="mt-3 text-sm font-semibold">{{ report.result_message }}</p>
                                <p class="mt-2 text-xs text-[#5f6c7b]">提交于 {{ report.created_at }}<span v-if="report.handled_at">，处理于 {{ report.handled_at }}</span></p>
                            </div>
                        </article>
                    </div>
                    <p v-else class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-6 text-sm text-[#5f6c7b]">没有符合条件的举报记录。</p>
                    <div class="flex justify-between gap-3">
                        <Link v-if="me.reports.links.prev" :href="me.reports.links.prev" class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold">上一页</Link><span v-else></span>
                        <Link v-if="me.reports.links.next" :href="me.reports.links.next" class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold">下一页</Link>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
