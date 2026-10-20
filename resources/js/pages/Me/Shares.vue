<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SharePanel from '@/components/SharePanel.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, Link } from '@inertiajs/vue3';
type NavProps = InstanceType<typeof UserCenterNav>['$props'];
defineProps<{
    me: {
        site: {
            name: string;
            logo_url: string | null;
            search_placeholder: string;
        };
        navigation: { label: string; url: string }[];
        user: NavProps['user'];
        summary: NavProps['summary'];
        effective_count: number;

    };
}>();
</script>
<template>
    <Head title="分享推广"
        ><meta name="robots" content="noindex,nofollow"
    /></Head>
    <div class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :site="me.site" :navigation="me.navigation" />
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <h1 class="mb-6 text-3xl font-bold">分享推广</h1>
            <div
                class="grid items-start gap-8 lg:grid-cols-[260px_minmax(0,1fr)]"
            >
                <UserCenterNav
                    :user="me.user"
                    :summary="me.summary"
                    active="shares"
                />
                <div class="min-w-0 space-y-8">
                    <section class="border-b border-[#90b4ce]/35 pb-7">
                        <h2 class="text-xl font-bold">
                            让更多人发现这份影像档案
                        </h2>
                        <p class="my-4 leading-7 text-[#5f6c7b]">
                            分享网站、图片、相册或专题，带来真实有效访问。累计
                            50 位有效访客，可自动获得对应启用的推广勋章。
                        </p>
                        <SharePanel
                            type="home"
                            target-key="home"
                            :title="me.site.name"
                            :blocked-reason="
                                me.user.is_banned
                                    ? '账号当前被限制，暂不能参与推广'
                                    : null
                            "
                        />
                        <p class="mt-6 font-semibold">
                            有效访客 {{ me.effective_count }} / 50 位<span
                                v-if="me.effective_count >= 50"
                            >
                                · 已达到获取条件</span
                            >
                        </p>
                        <progress
                            class="mt-3 h-3 w-full accent-[#3da9fc]"
                            :value="Math.min(50, me.effective_count)"
                            max="50"
                            aria-label="推广勋章进度"
                        ></progress
                        ><Link
                            href="/me/badges"
                            class="mt-3 inline-block text-sm underline"
                            >查看我的勋章</Link
                        >
                    </section>
                    <section class="rounded-sm bg-[#d8eefe]/40 p-5">
                        <h2 class="font-bold">哪些访问会计入？</h2>
                        <p class="mt-3 text-sm leading-7 text-[#5f6c7b]">
                            通过你的链接进入，页面可见累计至少 10
                            秒，并通过服务端验证。游客与已注册用户均可计入；同一可识别访客全站只归因一次，首次有效访问确定归属。排除本人、爬虫预览及异常访问，不要求提供截图。
                        </p>
                    </section>
                </div>
            </div>
        </main>
    </div>
</template>
