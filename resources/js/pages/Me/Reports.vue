<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

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
interface PhotoSummary {
    is_available: boolean;
    title: string;
    url: string | null;
    image_url: string | null;
    category_summary: string | null;
}
interface ReportItem {
    id: number;
    reason_label: string;
    status: string;
    status_label: string;
    details: string | null;
    result_message: string;
    created_at: string | null;
    handled_at: string | null;
    target: {
        type_label: string;
        comment_excerpt: string;
        comment_user_name: string | null;
        photo: PhotoSummary;
    };
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
    filters: { status: string };
    options: { statuses: OptionItem[]; reasons: OptionItem[] };
    reports: Paginated<ReportItem>;
}

const props = defineProps<{ me: MePayload }>();
const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
const isFirstEmpty = computed(
    () => props.me.summary.reports_count === 0 && !props.me.filters.status,
);
const form = reactive({ status: props.me.filters.status });
const applyFilters = () =>
    router.get(
        '/me/reports',
        { status: form.status || undefined },
        { preserveScroll: false, preserveState: false, replace: true },
    );
const resetFilters = () =>
    router.get(
        '/me/reports',
        {},
        { preserveScroll: false, preserveState: false, replace: true },
    );
</script>

<template>
    <Head title="我的举报" />
    <div ref="root" class="reports-page">
        <PublicHeader :navigation="me.navigation" :site="me.site" theme="afa" />
        <div class="reports-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="reports"
                variant="archive"
            />
            <main class="reports-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>我的举报</h1>
                        <p>查看自己提交过的举报和处理状态。</p>
                    </div>
                </div>
                <form class="report-filters" @submit.prevent="applyFilters">
                    <div class="report-filter">
                        <label for="report-status">处理状态</label
                        ><select
                            id="report-status"
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
                    <div class="report-filter-actions">
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
                        共 {{ me.reports.meta.total }} 条记录 · 第
                        {{ me.reports.meta.current_page }} /
                        {{ me.reports.meta.last_page }} 页
                    </p>
                    <p>按提交时间倒序</p>
                </div>
                <section
                    v-if="me.reports.data.length"
                    class="report-records"
                    aria-label="举报记录"
                >
                    <article
                        v-for="report in me.reports.data"
                        :key="report.id"
                        class="record"
                    >
                        <div class="record-heading">
                            <div class="record-meta">
                                <span class="record-type">{{
                                    report.reason_label
                                }}</span
                                ><span>提交于 {{ report.created_at }}</span>
                            </div>
                            <span
                                class="record-status"
                                :data-status="report.status"
                                >{{ report.status_label }}</span
                            >
                        </div>
                        <div class="record-layout">
                            <Link
                                :href="report.target.photo.url ?? '/me/reports'"
                                class="record-photo"
                                :aria-label="
                                    (report.target.photo.url
                                        ? '查看图片：'
                                        : '返回举报记录：') +
                                    report.target.photo.title
                                "
                                ><img
                                    v-if="report.target.photo.image_url"
                                    :src="report.target.photo.image_url"
                                    :alt="report.target.photo.title"
                                    loading="lazy" /><ImageOff
                                    v-else
                                    :size="24"
                                    aria-hidden="true"
                            /></Link>
                            <div class="record-body">
                                <div class="record-title-row">
                                    <h2 class="record-title">
                                        {{ report.target.photo.title }}
                                    </h2>
                                    <Link
                                        v-if="report.target.photo.url"
                                        :href="report.target.photo.url"
                                        class="photo-link"
                                        >查看图片</Link
                                    >
                                </div>
                                <dl class="target-excerpt">
                                    <dt>目标评论摘要</dt>
                                    <dd>
                                        {{
                                            report.target.comment_excerpt ||
                                            '内容暂不可见'
                                        }}
                                    </dd>
                                </dl>
                                <dl
                                    v-if="report.details"
                                    class="report-details"
                                >
                                    <dt>举报补充</dt>
                                    <dd>{{ report.details }}</dd>
                                </dl>
                                <div class="record-result">
                                    <strong>{{ report.result_message }}</strong
                                    ><span v-if="report.handled_at"
                                        >处理于 {{ report.handled_at }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </article>
                </section>
                <div v-else class="records-empty">
                    <h2>
                        {{
                            isFirstEmpty
                                ? '还没有举报记录'
                                : '没有符合条件的举报记录'
                        }}
                    </h2>
                    <p>
                        {{
                            isFirstEmpty
                                ? '你提交的举报与处理结果，会显示在这里。'
                                : '可以调整处理状态，再查看。'
                        }}
                    </p>
                    <Link
                        v-if="isFirstEmpty"
                        class="filter-clear"
                        href="/photos"
                        >浏览图库</Link
                    ><button
                        v-else
                        class="filter-clear"
                        type="button"
                        @click="resetFilters"
                    >
                        清空筛选条件
                    </button>
                </div>
                <ArchivePagination
                    :meta="me.reports.meta"
                    :links="me.reports.links"
                    label="举报记录分页"
                />
            </main>
        </div>
    </div>
</template>

<style scoped src="./Reports.css"></style>
