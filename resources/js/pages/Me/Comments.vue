<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import CommentContent from '@/components/CommentContent.vue';
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
interface CommentItem {
    id: number;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    content: string;
    correction_field_label: string | null;
    suggested_value: string | null;
    evidence_url: string | null;
    result_message: string;
    created_at: string | null;
    reviewed_at: string | null;
    photo: PhotoSummary;
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
    filters: { type: string; status: string };
    options: { types: OptionItem[]; statuses: OptionItem[] };
    comments: Paginated<CommentItem>;
}

const props = defineProps<{ me: MePayload }>();
const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
const hasFilters = computed(() =>
    Boolean(props.me.filters.type || props.me.filters.status),
);
const isFirstEmpty = computed(
    () =>
        props.me.summary.comments_count + props.me.summary.corrections_count ===
            0 && !hasFilters.value,
);
const form = reactive({
    type: props.me.filters.type,
    status: props.me.filters.status,
});
const applyFilters = () =>
    router.get(
        '/me/comments',
        { type: form.type || undefined, status: form.status || undefined },
        { preserveScroll: false, preserveState: false, replace: true },
    );
const resetFilters = () =>
    router.get(
        '/me/comments',
        {},
        { preserveScroll: false, preserveState: false, replace: true },
    );
</script>

<template>
    <Head title="我的评论与纠错" />
    <div ref="root" class="comments-page">
        <PublicHeader :navigation="me.navigation" :site="me.site" theme="afa" />
        <div class="comments-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="comments"
                variant="archive"
            />
            <main class="comments-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>我的评论与纠错</h1>
                        <p>查看提交内容、图片信息补充与审核结果。</p>
                    </div>
                </div>
                <form class="comment-filters" @submit.prevent="applyFilters">
                    <div class="comment-filter">
                        <label for="comment-type">记录类型</label
                        ><select
                            id="comment-type"
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
                    <div class="comment-filter">
                        <label for="comment-status">审核状态</label
                        ><select
                            id="comment-status"
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
                        共 {{ me.comments.meta.total }} 条记录 · 第
                        {{ me.comments.meta.current_page }} /
                        {{ me.comments.meta.last_page }} 页
                    </p>
                    <p>按提交时间倒序</p>
                </div>
                <section
                    v-if="me.comments.data.length"
                    class="comment-records"
                    aria-label="评论与纠错记录"
                >
                    <article
                        v-for="comment in me.comments.data"
                        :key="comment.id"
                        class="record"
                    >
                        <div class="record-heading">
                            <div class="record-meta">
                                <span class="record-type">{{
                                    comment.type_label
                                }}</span
                                ><span>提交于 {{ comment.created_at }}</span>
                            </div>
                            <span
                                class="record-status"
                                :data-status="comment.status"
                                >{{ comment.status_label }}</span
                            >
                        </div>
                        <div class="record-layout">
                            <Link
                                :href="comment.photo.url ?? '/me/comments'"
                                class="record-photo"
                                :aria-label="
                                    (comment.photo.url
                                        ? '查看图片：'
                                        : '返回评论记录：') +
                                    comment.photo.title
                                "
                                ><img
                                    v-if="comment.photo.image_url"
                                    :src="comment.photo.image_url"
                                    :alt="comment.photo.title"
                                    loading="lazy" /><ImageOff
                                    v-else
                                    :size="24"
                                    aria-hidden="true"
                            /></Link>
                            <div class="record-body">
                                <div class="record-title-row">
                                    <h2 class="record-title">
                                        {{ comment.photo.title }}
                                    </h2>
                                    <Link
                                        v-if="comment.photo.url"
                                        :href="comment.photo.url"
                                        class="photo-link"
                                        >查看图片</Link
                                    >
                                </div>
                                <p class="record-content">
                                    <CommentContent :text="comment.content" />
                                </p>
                                <dl
                                    v-if="
                                        comment.type === 'correction' &&
                                        (comment.correction_field_label ||
                                            comment.suggested_value ||
                                            comment.evidence_url)
                                    "
                                    class="correction-details"
                                >
                                    <template
                                        v-if="comment.correction_field_label"
                                        ><dt>字段</dt>
                                        <dd>
                                            {{ comment.correction_field_label }}
                                        </dd></template
                                    >
                                    <template v-if="comment.suggested_value"
                                        ><dt>建议值</dt>
                                        <dd>
                                            {{ comment.suggested_value }}
                                        </dd></template
                                    >
                                    <template v-if="comment.evidence_url"
                                        ><dt>证据链接</dt>
                                        <dd class="evidence-text">
                                            {{ comment.evidence_url }}
                                        </dd></template
                                    >
                                </dl>
                                <div class="record-result">
                                    <strong>{{ comment.result_message }}</strong
                                    ><span v-if="comment.reviewed_at"
                                        >处理于 {{ comment.reviewed_at }}</span
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
                                ? '还没有评论或纠错记录'
                                : '没有符合条件的评论记录'
                        }}
                    </h2>
                    <p>
                        {{
                            isFirstEmpty
                                ? '你提交的普通评论与图片信息补充／纠错，会显示在这里。'
                                : '可以调整记录类型或审核状态，再查看。'
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
                    :meta="me.comments.meta"
                    :links="me.comments.links"
                    label="评论记录分页"
                />
            </main>
        </div>
    </div>
</template>

<style scoped src="./Comments.css"></style>
