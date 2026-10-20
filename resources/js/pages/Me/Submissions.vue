<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SubmissionMetadataForm from '@/components/SubmissionMetadataForm.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import {
    emptySubmissionMetadata,
    type SubmissionDictionaries,
    type SubmissionMetadata,
} from '@/types/submissions';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
interface Submission {
    id: string;
    batch_id: string;
    status: string;
    status_label: string;
    metadata: SubmissionMetadata;
    revision: number;
    original_filename: string;
    review_note: string | null;
    error_message: string | null;
    contribution_revoked: boolean;
    contribution_note: string | null;
    cleaned: boolean;
    preview_url: string | null;
    cleanup_at: string | null;
    created_at: string;
    photo_url: string | null;
}
interface PendingFile {
    id: string;
    file: File;
    url: string;
    progress: number;
    status: 'waiting' | 'uploading' | 'uploaded' | 'error';
    error: string;
    metadata: SubmissionMetadata;
}
const props = defineProps<{
    me: SubmissionDictionaries & {
        site: {
            name: string;
            logo_url: string | null;
            search_placeholder: string;
        };
        navigation: { label: string; url: string }[];
        user: InstanceType<typeof UserCenterNav>['$props']['user'];
        summary: InstanceType<typeof UserCenterNav>['$props']['summary'];
        batch_summaries: {
            batch_id: string;
            total: number;
            accepted: number;
            rejected: number;
            waiting: number;
        }[];
        can_submit: boolean;
        can_apply: boolean;
        contribution_count: number;
        applications: {
            id: number;
            reason: string;
            status: string;
            status_label: string;
            review_note: string | null;
            created_at: string;
        }[];
        submissions: {
            data: Submission[];
            last_page: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
}>();
const application = useForm({ reason: '' });
const common = ref(emptySubmissionMetadata());
const files = ref<PendingFile[]>([]);
const batch = ref(crypto.randomUUID());
const uploadBusy = ref(false);
const statusMessage = ref('');
const submit = useForm<{
    items: { id: string; metadata: SubmissionMetadata }[];
}>({ items: [] });
const edit = useForm({ metadata: emptySubmissionMetadata(), revision: 0 });
const editingId = ref<string | null>(null);
const latestApplication = computed(() => props.me.applications[0]);
const cloneMeta = (meta: SubmissionMetadata): SubmissionMetadata => ({
    ...meta,
    category_ids: [...meta.category_ids],
    tag_ids: [...meta.tag_ids],
});
const buttonClass =
    'min-h-11 rounded-sm border border-[#094067] px-4 py-2 text-sm font-semibold text-[#094067] transition hover:bg-[#d8eefe] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
function addFiles(selection: FileList | File[]) {
    for (const file of Array.from(selection)) {
        if (files.value.length >= 20) {
            statusMessage.value = '单批最多 20 张。';
            break;
        }
        if (
            !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(
                file.type,
            ) ||
            file.size > 5 * 1024 * 1024
        ) {
            statusMessage.value = `${file.name}：格式不支持或超过 5MB。`;
            continue;
        }
        if (
            files.value.some(
                (item) =>
                    item.file.name === file.name &&
                    item.file.size === file.size &&
                    item.file.lastModified === file.lastModified,
            )
        ) {
            statusMessage.value = '已跳过重复选择的文件。';
            continue;
        }
        files.value.push({
            id: crypto.randomUUID(),
            file,
            url: URL.createObjectURL(file),
            progress: 0,
            status: 'waiting',
            error: '',
            metadata: cloneMeta(common.value),
        });
    }
}
function removeFile(item: PendingFile) {
    if (item.status === 'uploaded')
        router.post(
            `/me/submissions/${item.id}/withdraw`,
            {},
            { preserveScroll: true },
        );
    URL.revokeObjectURL(item.url);
    files.value = files.value.filter((file) => file !== item);
}
function uploadOne(item: PendingFile): Promise<void> {
    return new Promise((resolve) => {
        item.status = 'uploading';
        item.error = '';
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/me/submissions/upload');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        const token = document.cookie
            .split('; ')
            .find((value) => value.startsWith('XSRF-TOKEN='))
            ?.split('=')
            .slice(1)
            .join('=');
        if (token)
            xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(token));
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable)
                item.progress = Math.round((event.loaded / event.total) * 100);
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                item.status = 'uploaded';
                item.progress = 100;
            } else {
                item.status = 'error';
                try {
                    const data = JSON.parse(xhr.responseText);
                    item.error =
                        Object.values(data.errors ?? {})
                            .flat()
                            .join('；') ||
                        data.message ||
                        '上传失败';
                } catch {
                    item.error = '上传失败，请重试。';
                }
                if (xhr.status === 422) item.id = crypto.randomUUID();
                if (xhr.status === 403)
                    item.error = '投稿资格或账号状态已变化，请刷新页面查看。';
                if (xhr.status === 419)
                    item.error = '登录或表单凭证已过期，请刷新页面后重试。';
            }
            resolve();
        };
        xhr.onerror = () => {
            item.status = 'error';
            item.error = '网络异常，可单独重试。';
            resolve();
        };
        const data = new FormData();
        data.append('id', item.id);
        data.append('batch_id', batch.value);
        data.append('file', item.file);
        xhr.send(data);
    });
}
async function uploadAll() {
    uploadBusy.value = true;
    for (const item of files.value)
        if (item.status !== 'uploaded') await uploadOne(item);
    uploadBusy.value = false;
}
async function retryFile(item: PendingFile) {
    if (uploadBusy.value) return;
    uploadBusy.value = true;
    await uploadOne(item);
    uploadBusy.value = false;
}
function submitFiles() {
    if (
        !files.value.length ||
        files.value.some((item) => item.status !== 'uploaded')
    ) {
        statusMessage.value = '请先上传全部选中图片，失败图片可重试或移除。';
        return;
    }
    submit.items = files.value.map((item) => ({
        id: item.id,
        metadata: item.metadata,
    }));
    submit.post('/me/submissions', {
        preserveScroll: true,
        onSuccess: () => {
            files.value.forEach((item) => URL.revokeObjectURL(item.url));
            files.value = [];
            batch.value = crypto.randomUUID();
            statusMessage.value = '投稿已提交，等待审核。';
        },
    });
}
function openEdit(row: Submission) {
    editingId.value = row.id;
    edit.metadata = cloneMeta(row.metadata);
    edit.revision = row.revision;
    edit.clearErrors();
}
const timer = window.setInterval(() => {
    if (
        !uploadBusy.value &&
        !editingId.value &&
        (props.me.submissions.data.some((row) =>
            ['pending', 'processing', 'failed'].includes(row.status),
        ) ||
            latestApplication.value?.status === 'pending')
    )
        router.reload({ only: ['me'] });
}, 30000);
onBeforeUnmount(() => {
    window.clearInterval(timer);
    files.value.forEach((item) => URL.revokeObjectURL(item.url));
});
</script>
<template>
    <Head title="图片投稿" />
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="me.navigation" :site="me.site" />
        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <h1 class="text-3xl font-semibold sm:text-4xl">图片投稿</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b]">
                    分享值得保存的影像资料。投稿经审核、整理后发布，采纳累计 50
                    张可获得贡献勋章。
                </p>
            </div>
        </section>
        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[280px_minmax(0,1fr)]"
            >
                <UserCenterNav
                    :user="me.user"
                    :summary="me.summary"
                    active="submissions"
                />
                <div class="min-w-0 space-y-8">
                    <p
                        v-if="statusMessage"
                        role="status"
                        class="rounded-sm bg-[#d8eefe] p-4 text-sm"
                    >
                        {{ statusMessage }}
                    </p>
                    <section v-if="!me.can_submit" class="space-y-4">
                        <h2 class="text-xl font-semibold">申请投稿资格</h2>
                        <p class="text-sm leading-7 text-[#5f6c7b]">
                            普通用户需先申请成为投稿者。资格通过后可提交图片，不会获得后台管理或发布权限。
                        </p>
                        <p v-if="latestApplication" class="text-sm">
                            最近申请：{{ latestApplication.status_label }} ·
                            {{ latestApplication.created_at
                            }}<span
                                v-if="latestApplication.review_note"
                                class="mt-2 block"
                                >处理原因：{{
                                    latestApplication.review_note
                                }}</span
                            >
                        </p>
                        <form
                            v-if="
                                me.can_apply &&
                                latestApplication?.status !== 'pending'
                            "
                            class="space-y-3"
                            @submit.prevent="
                                application.post('/me/submissions/apply', {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <label
                                for="application-reason"
                                class="block text-sm"
                                >申请理由（10—2000字）</label
                            ><textarea
                                id="application-reason"
                                v-model="application.reason"
                                required
                                minlength="10"
                                maxlength="2000"
                                rows="4"
                                class="w-full rounded-sm border border-[#90b4ce] bg-white p-3 text-sm"
                            />
                            <p
                                v-if="application.errors.reason"
                                role="alert"
                                class="text-sm text-red-700"
                            >
                                {{ application.errors.reason }}
                            </p>
                            <button
                                :class="buttonClass"
                                :disabled="application.processing"
                            >
                                提交资格申请
                            </button>
                        </form>
                        <p
                            v-else-if="latestApplication?.status === 'pending'"
                            class="text-sm"
                        >
                            申请正在审核，无需重复提交。
                        </p>
                        <p v-else class="text-sm">
                            当前账号状态不允许申请或上传，可继续查看历史投稿。
                        </p>
                    </section>
                    <section v-if="me.can_submit" class="space-y-5">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <h2 class="text-xl font-semibold">提交图片</h2>
                            <p class="text-sm">
                                已采纳 {{ me.contribution_count }} / 50 张
                            </p>
                        </div>
                        <p class="text-sm leading-7 text-[#5f6c7b]">
                            支持 JPG、PNG、WebP、GIF，单张最大 5MB，单批最多 20
                            张，每日最多成功提交 100
                            张。资料不确定可以留空，来源不详请如实说明。
                        </p>
                        <details
                            class="rounded-sm border border-[#90b4ce]/50 p-4"
                            open
                        >
                            <summary
                                class="cursor-pointer text-sm font-semibold"
                            >
                                整批资料（先填写，再选择图片）
                            </summary>
                            <div class="mt-4">
                                <SubmissionMetadataForm
                                    v-model="common"
                                    :dictionaries="me"
                                    id-prefix="batch"
                                />
                            </div>
                            <button
                                type="button"
                                :class="[buttonClass, 'mt-4']"
                                :disabled="!files.length || uploadBusy"
                                @click="
                                    files.forEach(
                                        (item) =>
                                            (item.metadata = cloneMeta(common)),
                                    )
                                "
                            >
                                将整批资料应用到所有选中图片
                            </button>
                            <p class="mt-2 text-xs text-[#5f6c7b]">
                                应用后可在下方逐张调整；再次应用会覆盖单张填写的资料。
                            </p>
                        </details>
                        <div
                            class="rounded-sm border-2 border-dashed border-[#90b4ce] p-6 text-center"
                            @dragover.prevent
                            @drop.prevent="
                                !uploadBusy &&
                                addFiles($event.dataTransfer?.files ?? [])
                            "
                        >
                            <label
                                class="inline-flex min-h-11 cursor-pointer items-center gap-2 font-semibold"
                                >选择或拖拽图片<input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    multiple
                                    class="block max-w-full text-sm"
                                    :disabled="uploadBusy"
                                    @change="
                                        addFiles(
                                            ($event.target as HTMLInputElement)
                                                .files ?? [],
                                        );
                                        (
                                            $event.target as HTMLInputElement
                                        ).value = '';
                                    "
                            /></label>
                        </div>
                        <article
                            v-for="(item, index) in files"
                            :key="item.url"
                            class="border-b border-[#90b4ce]/35 py-4"
                        >
                            <div class="flex items-start gap-4">
                                <img
                                    :src="item.url"
                                    alt="待投稿预览"
                                    class="h-24 w-24 shrink-0 object-contain"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold break-all">
                                        {{ index + 1 }}. {{ item.file.name }}
                                    </p>
                                    <p class="mt-1 text-sm">
                                        {{
                                            item.status === 'uploaded'
                                                ? '已上传，待提交审核'
                                                : item.status === 'error'
                                                  ? '上传失败'
                                                  : item.status === 'uploading'
                                                    ? `上传中 ${item.progress}%`
                                                    : '待上传'
                                        }}
                                    </p>
                                    <progress
                                        v-if="item.status === 'uploading'"
                                        :value="item.progress"
                                        max="100"
                                        class="mt-2 w-full"
                                        aria-label="上传进度"
                                    />
                                    <p
                                        v-if="item.error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700"
                                    >
                                        {{ item.error }}
                                    </p>
                                    <div class="mt-2 flex gap-3">
                                        <button
                                            v-if="item.status === 'error'"
                                            type="button"
                                            :class="buttonClass"
                                            :disabled="uploadBusy"
                                            @click="retryFile(item)"
                                        >
                                            重试此图片</button
                                        ><button
                                            type="button"
                                            :class="buttonClass"
                                            :disabled="
                                                uploadBusy ||
                                                item.status === 'uploading'
                                            "
                                            @click="removeFile(item)"
                                        >
                                            移除
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <details class="mt-3">
                                <summary
                                    class="min-h-11 cursor-pointer text-sm font-semibold"
                                >
                                    调整此图片资料
                                </summary>
                                <SubmissionMetadataForm
                                    v-model="item.metadata"
                                    :dictionaries="me"
                                    :id-prefix="item.id"
                                />
                            </details>
                        </article>
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                :class="buttonClass"
                                :disabled="!files.length || uploadBusy"
                                @click="uploadAll"
                            >
                                {{
                                    uploadBusy ? '上传中…' : '上传选中图片'
                                }}</button
                            ><button
                                type="button"
                                :class="[buttonClass, 'bg-[#d8eefe]']"
                                :disabled="
                                    !files.length ||
                                    uploadBusy ||
                                    submit.processing
                                "
                                @click="submitFiles"
                            >
                                提交审核
                            </button>
                        </div>
                        <p
                            v-for="(message, key) in submit.errors"
                            :key="key"
                            role="alert"
                            class="text-sm text-red-700"
                        >
                            {{ message }}
                        </p>
                        <p class="text-xs leading-6 text-[#5f6c7b]">
                            提交不代表拥有图片版权，也不会直接公开。未完成提交的暂存文件
                            24 小时后清理；未采纳或撤回的文件保留 7
                            天，之后删除文件、保留文字结果。
                        </p>
                    </section>
                    <section class="space-y-4">
                        <h2 class="text-xl font-semibold">我的投稿</h2>
                        <p class="text-sm text-[#5f6c7b]">
                            累计采纳
                            {{ me.contribution_count }}
                            张；每张图片独立审核，同批次可以部分采纳。
                        </p>
                        <details
                            v-if="me.batch_summaries.length"
                            class="text-sm"
                        >
                            <summary
                                class="min-h-11 cursor-pointer font-semibold"
                            >
                                本页批次统计
                            </summary>
                            <p
                                v-for="batchSummary in me.batch_summaries"
                                :key="batchSummary.batch_id"
                                class="mt-2 leading-6"
                            >
                                批次 {{ batchSummary.batch_id.slice(0, 8) }}：共
                                {{ batchSummary.total }} 张，采纳
                                {{ batchSummary.accepted }} 张，未采纳
                                {{ batchSummary.rejected }} 张，待审核或处理中
                                {{ batchSummary.waiting }} 张。
                            </p>
                        </details>
                        <p
                            v-if="!me.submissions.data.length"
                            class="py-6 text-sm text-[#5f6c7b]"
                        >
                            还没有已提交的投稿。
                        </p>
                        <article
                            v-for="row in me.submissions.data"
                            :key="row.id"
                            class="border-b border-[#90b4ce]/35 py-4"
                        >
                            <div class="flex items-start gap-4">
                                <img
                                    v-if="row.preview_url"
                                    :src="row.preview_url"
                                    alt="本人投稿"
                                    class="h-24 w-24 shrink-0 object-contain"
                                    loading="lazy"
                                /><span
                                    v-else
                                    class="flex h-24 w-24 shrink-0 items-center justify-center bg-[#d8eefe] text-xs"
                                    >文件已清理</span
                                >
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold break-all">
                                        {{
                                            row.metadata?.title ||
                                            row.original_filename
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm">
                                        {{ row.status_label }} ·
                                        {{ row.created_at }} · 批次
                                        {{ row.batch_id.slice(0, 8) }}
                                    </p>
                                    <p
                                        v-if="row.review_note"
                                        class="mt-2 text-sm"
                                    >
                                        审核结果：{{ row.review_note }}
                                    </p>
                                    <p
                                        v-if="row.contribution_revoked"
                                        class="mt-2 text-sm text-red-700"
                                    >
                                        贡献计数已纠正：{{
                                            row.contribution_note
                                        }}
                                    </p>
                                    <details class="mt-3 text-sm">
                                        <summary
                                            class="min-h-11 cursor-pointer font-semibold"
                                        >
                                            查看投稿资料
                                        </summary>
                                        <p class="mt-2">
                                            图片日期：{{
                                                row.metadata?.event_date ||
                                                '不详'
                                            }}
                                        </p>
                                        <p class="mt-2">
                                            来源：{{
                                                row.metadata?.source_note
                                            }}
                                        </p>
                                        <a
                                            v-if="row.metadata?.source_url"
                                            :href="row.metadata.source_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-2 inline-block underline"
                                            >查看来源链接</a
                                        >
                                        <p class="mt-2">
                                            说明：{{
                                                row.metadata?.description ||
                                                '无'
                                            }}
                                        </p>
                                        <p class="mt-2">
                                            署名选择：{{
                                                row.metadata?.show_credit
                                                    ? '自愿署名'
                                                    : '匿名'
                                            }}
                                        </p>
                                        <p class="mt-2">
                                            分类：{{
                                                me.categories
                                                    .flatMap(
                                                        (category) =>
                                                            category.children,
                                                    )
                                                    .filter((category) =>
                                                        row.metadata?.category_ids.includes(
                                                            category.id,
                                                        ),
                                                    )
                                                    .map(
                                                        (category) =>
                                                            category.name,
                                                    )
                                                    .join('、') || '未填写'
                                            }}
                                        </p>
                                        <p class="mt-2">
                                            相册：{{
                                                me.albums.find(
                                                    (album) =>
                                                        album.id ===
                                                        row.metadata?.album_id,
                                                )?.title || '未选择或已调整'
                                            }}
                                        </p>
                                        <p class="mt-2">
                                            标签：{{
                                                me.tags
                                                    .filter((tag) =>
                                                        row.metadata?.tag_ids.includes(
                                                            tag.id,
                                                        ),
                                                    )
                                                    .map((tag) => tag.name)
                                                    .join('、') || '未填写'
                                            }}
                                        </p>
                                    </details>
                                    <p
                                        v-if="row.error_message"
                                        class="mt-2 text-sm text-red-700"
                                    >
                                        {{ row.error_message }}
                                    </p>
                                    <p
                                        v-if="
                                            row.cleanup_at &&
                                            !row.cleaned &&
                                            ['withdrawn', 'rejected'].includes(
                                                row.status,
                                            )
                                        "
                                        class="mt-2 text-xs text-[#5f6c7b]"
                                    >
                                        暂存文件计划清理时间：{{
                                            row.cleanup_at
                                        }}（北京时间）
                                    </p>
                                    <Link
                                        v-if="row.photo_url"
                                        :href="row.photo_url"
                                        class="mt-2 inline-block text-sm underline"
                                        >查看已发布图片</Link
                                    >
                                    <p
                                        v-else-if="row.status === 'accepted'"
                                        class="mt-2 text-sm"
                                    >
                                        已采纳，尚未公开或暂不可见。
                                    </p>
                                    <div
                                        v-if="row.status === 'pending'"
                                        class="mt-3 flex flex-wrap gap-3"
                                    >
                                        <button
                                            v-if="me.can_submit"
                                            :class="buttonClass"
                                            @click="openEdit(row)"
                                        >
                                            修改资料</button
                                        ><button
                                            :class="buttonClass"
                                            @click="
                                                router.post(
                                                    `/me/submissions/${row.id}/withdraw`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            "
                                        >
                                            撤回投稿
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <form
                                v-if="editingId === row.id"
                                class="mt-5 space-y-3"
                                @submit.prevent="
                                    edit.patch(`/me/submissions/${row.id}`, {
                                        preserveScroll: true,
                                        onSuccess: () => (editingId = null),
                                    })
                                "
                            >
                                <SubmissionMetadataForm
                                    v-model="edit.metadata"
                                    :dictionaries="me"
                                    :id-prefix="`edit-${row.id}`"
                                />
                                <p
                                    v-for="(message, key) in edit.errors"
                                    :key="key"
                                    role="alert"
                                    class="text-sm text-red-700"
                                >
                                    {{ message }}
                                </p>
                                <button
                                    :class="buttonClass"
                                    :disabled="edit.processing"
                                >
                                    保存修改</button
                                ><button
                                    type="button"
                                    :class="[buttonClass, 'ml-3']"
                                    @click="editingId = null"
                                >
                                    取消
                                </button>
                            </form>
                        </article>
                        <nav
                            v-if="me.submissions.last_page > 1"
                            class="flex flex-wrap gap-2"
                            aria-label="投稿记录分页"
                        >
                            <template
                                v-for="(link, index) in me.submissions.links"
                                :key="index"
                                ><Link
                                    v-if="link.url"
                                    :href="link.url"
                                    :aria-current="
                                        link.active ? 'page' : undefined
                                    "
                                    class="min-h-11 rounded-sm border border-[#90b4ce]/50 px-3 py-2 text-sm"
                                    :class="{ 'bg-[#d8eefe]': link.active }"
                                    >{{
                                        link.label
                                            .replace(/&laquo;/g, '‹')
                                            .replace(/&raquo;/g, '›')
                                    }}</Link
                                ></template
                            >
                        </nav>
                    </section>
                    <details
                        v-if="me.applications.length"
                        class="border-t border-[#90b4ce]/35 pt-4"
                    >
                        <summary class="cursor-pointer text-sm font-semibold">
                            资格申请历史
                        </summary>
                        <p
                            v-for="record in me.applications"
                            :key="record.id"
                            class="mt-3 text-sm leading-6"
                        >
                            {{ record.created_at }} ·
                            {{ record.status_label }} · {{ record.reason
                            }}<span
                                v-if="record.review_note"
                                class="block text-[#5f6c7b]"
                                >{{ record.review_note }}</span
                            >
                        </p>
                    </details>
                </div>
            </div>
        </section>
    </main>
</template>
