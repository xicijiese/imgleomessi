<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link, router } from '@inertiajs/vue3';

import { onMounted, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface PlanItem {
    id: number;
    name: string;
    amount_label: string;
    duration_label: string;
    badge_label: string;
    benefits: string[];
}

interface SupportPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    footer: {
        copyright_text: string;
        icp_text: string | null;
        links: NavigationItem[];
        social_links: NavigationItem[];
    };
    payment_enabled: boolean;
    plans: PlanItem[];
    auth: {
        can_support: boolean;
        is_supporter: boolean;
        supporter_until: string | null;
    };
}

defineProps<{
    support: SupportPayload;
}>();

const processingPlanId = ref<number | null>(null);
const error = ref('');

const createOrder = (plan: PlanItem) => {
    processingPlanId.value = plan.id;
    router.post(
        '/support/orders',
        { plan_id: plan.id },
        {
            preserveScroll: false,
            onError: (errors) => {
                error.value =
                    Object.values(errors)[0] ?? '订单创建失败，请稍后重试。';
            },
            onFinish: () => {
                processingPlanId.value = null;
            },
        },
    );
};

const showParticipation = ref(false);
onMounted(() => {
    showParticipation.value = window.matchMedia('(min-width: 768px)').matches;
});

import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import '../../../css/archive-browser.css';
const pageRoot = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(pageRoot);
</script>
<template>
    <SeoHead :seo="support.seo" />
    <main ref="pageRoot" class="archive-browser archive-support">
        <PublicHeader
            :site="support.site"
            :navigation="support.navigation"
            theme="afa"
        />
        <div class="support-main">
            <div class="support-revised">
                <div class="support-hero">
                    <section class="intro browser-heading">
                        <h1>支持本站维护</h1>
                        <p>
                            赞助只用于支持资料整理、服务器、存储和后续功能维护，不代表购买图片版权，也不会解锁原图下载权益。
                        </p>
                        <p>
                            运营成本之外的结余，将用于捐赠给公益机构；相关捐赠信息会在条件允许时公开。
                        </p>
                    </section>
                    <aside
                        class="payment-panel"
                        aria-labelledby="payment-heading"
                    >
                        <h2 id="payment-heading">支持方式</h2>
                        <dl>
                            <div>
                                <dt>支付方式</dt>
                                <dd>
                                    {{
                                        support.payment_enabled
                                            ? '微信支付'
                                            : '微信收款暂未开放'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt>扣款方式</dt>
                                <dd>自愿赞助，不自动扣款</dd>
                            </div>
                            <div>
                                <dt>退款申请</dt>
                                <dd>通过站点联系方式申请</dd>
                            </div>
                        </dl>
                        <p>仅限正常登录用户自愿赞助。</p>
                        <p
                            v-if="support.auth.is_supporter"
                            class="existing-supporter"
                        >
                            你已是运营守护者<span
                                v-if="support.auth.supporter_until"
                                >，有效期至
                                {{ support.auth.supporter_until }}</span
                            >
                        </p>
                    </aside>
                </div>
                <section class="plan-section">
                    <div class="section-title">
                        <div>
                            <h2>选择支持方案</h2>
                            <p>金额、身份时长及支持内容如下。</p>
                        </div>
                        <span class="small-assurance"
                            >自愿支持，不自动扣款</span
                        >
                    </div>
                    <p v-if="error" class="error" role="alert">{{ error }}</p>
                    <div class="plans">
                        <article
                            v-for="plan in support.plans"
                            :key="plan.id"
                            class="plan"
                        >
                            <h3>{{ plan.name }}</h3>
                            <p class="duration">{{ plan.duration_label }}</p>
                            <div class="amount">{{ plan.amount_label }}</div>
                            <span class="badge-label">{{
                                plan.badge_label
                            }}</span>
                            <ul>
                                <li
                                    v-for="benefit in plan.benefits"
                                    :key="benefit"
                                >
                                    {{ benefit }}
                                </li>
                            </ul>
                            <button
                                v-if="support.auth.can_support"
                                type="button"
                                class="action gold"
                                :disabled="
                                    processingPlanId !== null ||
                                    !support.payment_enabled
                                "
                                @click="createOrder(plan)"
                            >
                                {{
                                    processingPlanId === plan.id
                                        ? '创建订单中'
                                        : '选择支持'
                                }}</button
                            ><Link v-else class="action gold" href="/login"
                                >登录后支持</Link
                            >
                        </article>
                    </div>
                </section>
                <section class="participation-section participation-revised">
                    <details
                        class="participation-info"
                        :open="showParticipation"
                        @toggle="
                            showParticipation = (
                                $event.target as HTMLDetailsElement
                            ).open
                        "
                    >
                        <summary>
                            <span>参与方式说明</span
                            ><span class="disclosure-action"
                                ><span class="when-closed">展开说明</span
                                ><span class="when-open">收起说明</span></span
                            >
                        </summary>
                        <div class="participation-content">
                            <p>
                                影像档案的长期整理，既需要运营成本，也需要愿意投入时间和资料的同行者。你可以按自己的方式参与，共同让这些记忆更完整。
                            </p>
                            <div class="participation-roles">
                                <section>
                                    <h3>运营守护者</h3>
                                    <p>
                                        通过赞助分担服务器、对象存储、图片处理和日常维护成本。运营守护者不代表购买图片版权，也不会获得原图下载或转载授权。
                                    </p>
                                </section>
                                <section>
                                    <h3>档案共建者</h3>
                                    <p>
                                        用图片库存、资料考据和整理能力，参与图片、相册、分类、标签与来源信息的维护。档案共建者由管理员在私域确认后邀请加入，不开放公开申请。
                                    </p>
                                </section>
                            </div>
                        </div>
                    </details>
                </section>
                <div class="support-guidance">
                    <section class="support-terms">
                        <h2>支持须知</h2>
                        <dl>
                            <div>
                                <dt>不售卖版权</dt>
                                <dd>
                                    赞助不会赋予图片下载、转载、商用或授权使用权。
                                </dd>
                            </div>
                            <div>
                                <dt>身份展示可关闭</dt>
                                <dd>
                                    运营守护者可在个人中心决定是否出现在致谢墙。
                                </dd>
                            </div>
                        </dl>
                    </section>
                    <section
                        class="wall-entry"
                        aria-labelledby="wall-entry-heading"
                    >
                        <div>
                            <h2 id="wall-entry-heading">公开致谢</h2>
                            <p>
                                这里只展示愿意公开昵称的运营守护者和档案共建者。
                            </p>
                        </div>
                        <Link
                            class="action wall-entry-button"
                            href="/supporters"
                            >查看致谢墙</Link
                        >
                    </section>
                </div>
            </div>
        </div>
    </main>
</template>
<style scoped src="../../../css/support-pages.css"></style>
