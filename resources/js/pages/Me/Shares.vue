<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SharePanel from '@/components/SharePanel.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Share2 } from 'lucide-vue-next';
import { ref } from 'vue';
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
const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
</script>
<template>
    <Head title="分享推广"
        ><meta name="robots" content="noindex,nofollow"
    /></Head>
    <div ref="root" class="shares-page">
        <PublicHeader :site="me.site" :navigation="me.navigation" theme="afa" />
        <div class="shares-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="shares"
                variant="archive"
            />
            <main class="share-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>分享推广</h1>
                        <p>让更多人发现这份影像档案。</p>
                    </div>
                </div>
                <section class="share-main">
                    <div class="share-intro">
                        <h2>把值得回看的瞬间，分享出去</h2>
                        <p>
                            分享网站、图片、相册或专题，带来真实有效访问。每一次被认真浏览的分享，都让这份档案被更多人看见。
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
                        >
                            <template #trigger="{ open }"
                                ><button
                                    class="primary"
                                    type="button"
                                    :disabled="me.user.is_banned"
                                    @click="open"
                                >
                                    <Share2
                                        :size="18"
                                        aria-hidden="true"
                                    />分享网站
                                </button></template
                            >
                        </SharePanel>
                        <p class="helper">图片、相册和专题可在对应页面分享。</p>
                        <p
                            v-if="me.user.is_banned"
                            id="restriction"
                            role="status"
                        >
                            账号当前被限制，暂不能参与推广。
                        </p>
                    </div>
                    <div class="progress-panel">
                        <h2>有效访客</h2>
                        <p class="figure">
                            <strong>{{ me.effective_count }}</strong
                            ><span> / 50 位</span>
                        </p>
                        <progress
                            max="50"
                            :value="Math.min(50, me.effective_count)"
                            aria-label="推广勋章进度"
                        ></progress>
                        <p id="remaining">
                            {{
                                me.effective_count >= 50
                                    ? '已达到获取条件'
                                    : me.effective_count === 0
                                      ? '从第一次有效访问开始累计'
                                      : `距获取条件还差${50 - me.effective_count}位有效访客`
                            }}
                        </p>
                        <p class="helper">
                            累计50位有效访客，可自动获得对应启用的推广勋章。
                        </p>
                        <Link class="secondary badge-link" href="/me/badges"
                            >查看我的勋章
                            <ArrowRight :size="16" aria-hidden="true"
                        /></Link>
                    </div>
                </section>
                <section class="rules">
                    <h2>哪些访问会计入？</h2>
                    <div class="rule-row">
                        <h3>认真浏览，才算有效</h3>
                        <p>
                            通过你的链接进入，页面可见累计至少10秒，并通过服务端验证。游客与已注册用户均可计入。
                        </p>
                    </div>
                    <div class="rule-row">
                        <h3>同一访客，只归因一次</h3>
                        <p>
                            同一可识别访客全站只归因一次，首次有效访问确定归属。
                        </p>
                    </div>
                    <div class="rule-row">
                        <h3>分享操作不等于有效人数</h3>
                        <p>
                            排除本人、爬虫预览及异常访问。不要求提供截图；复制链接、打开渠道或生成海报本身不会增加有效访客。
                        </p>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
<style scoped src="./Shares.css"></style>
