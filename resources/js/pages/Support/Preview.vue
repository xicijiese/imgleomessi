<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import { Link } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { ArrowUpRight, BookOpen, Check, Heart } from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import '../../../css/archive-browser.css';
import friendQr from '../../../images/social/wechat-friend-qr.png';
interface Plan {
    id: number;
    name: string;
    amount_label: string;
    duration_label: string;
    badge_label: string;
    benefits: string[];
}
defineProps<{
    support: {
        site: {
            name: string;
            logo_url: string | null;
            search_placeholder: string;
        };
        navigation: { label: string; url: string }[];
        plans: Plan[];
        payment_enabled: boolean;
        auth: { is_supporter: boolean; supporter_until: string | null };
    };
}>();
const root = ref<HTMLElement | null>(null);
const selected = ref<number | null>(null);
const activeTab = ref<'guardian' | 'contributor'>('guardian');
const showContactQr = ref(false);
let panelTween: gsap.core.Tween | undefined;
const switchTab = async (tab: 'guardian' | 'contributor', focus = false) => {
    if (activeTab.value === tab) return;
    panelTween?.revert();
    showContactQr.value = false;
    activeTab.value = tab;
    await nextTick();
    if (focus)
        root.value
            ?.querySelector<HTMLButtonElement>('#support-tab-' + tab)
            ?.focus();
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        panelTween = gsap.fromTo(
            root.value?.querySelector('#support-panel-' + tab),
            { opacity: 0, y: 8 },
            {
                opacity: 1,
                y: 0,
                duration: 0.22,
                clearProps: 'opacity,transform',
            },
        );
    }
    ScrollTrigger.refresh();
};
let motion: gsap.MatchMedia | undefined;
onMounted(() => {
    gsap.registerPlugin(ScrollTrigger);
    motion = gsap.matchMedia();
    motion.add('(prefers-reduced-motion: no-preference)', () => {
        const ctx = gsap.context(() => {
            gsap.from('.intro', {
                y: 12,
                opacity: 0,
                duration: 0.45,
                clearProps: 'all',
            });
            gsap.utils.toArray<HTMLElement>('.reveal').forEach((el) =>
                gsap.from(el, {
                    y: 16,
                    opacity: 0,
                    duration: 0.5,
                    ease: 'power2.out',
                    clearProps: 'all',
                    scrollTrigger: {
                        trigger: el,
                        start: 'top 94%',
                        once: true,
                    },
                }),
            );
        }, root.value!);
        return () => ctx.revert();
    });
});
onUnmounted(() => {
    panelTween?.revert();
    motion?.revert();
});
</script>
<template>
    <main ref="root" class="support-preview archive-browser">
        <PublicHeader
            :site="support.site"
            :navigation="support.navigation"
            theme="afa"
        />
        <div class="page-shell">
            <header class="intro">
                <span class="preview-note">布局示例 · 不发起支付</span>
                <h1>支持本站维护</h1>
                <p>
                    支持资料整理、服务器、图片存储和日常维护，让影像档案持续完善。
                </p>
            </header>
            <section class="ways reveal" aria-labelledby="ways-heading">
                <div class="section-title">
                    <h2 id="ways-heading">两种支持方式</h2>
                </div>
                <div class="role-tabs" role="tablist" aria-label="选择支持方式">
                    <button
                        id="support-tab-guardian"
                        role="tab"
                        :aria-selected="activeTab === 'guardian'"
                        aria-controls="support-panel-guardian"
                        :tabindex="activeTab === 'guardian' ? 0 : -1"
                        @click="switchTab('guardian')"
                        @keydown.right.prevent="switchTab('contributor', true)"
                        @keydown.left.prevent="switchTab('contributor', true)"
                    >
                        <Heart :size="21" /><span
                            >运营守护者<small>赞助运营与维护</small></span
                        ><Check v-if="activeTab === 'guardian'" :size="17" />
                    </button>
                    <button
                        id="support-tab-contributor"
                        role="tab"
                        :aria-selected="activeTab === 'contributor'"
                        aria-controls="support-panel-contributor"
                        :tabindex="activeTab === 'contributor' ? 0 : -1"
                        @click="switchTab('contributor')"
                        @keydown.right.prevent="switchTab('guardian', true)"
                        @keydown.left.prevent="switchTab('guardian', true)"
                    >
                        <BookOpen :size="21" /><span
                            >档案共建者<small>分享图片与资料</small></span
                        ><Check v-if="activeTab === 'contributor'" :size="17" />
                    </button>
                </div>
            </section>
            <section
                id="support-panel-guardian"
                v-show="activeTab === 'guardian'"
                role="tabpanel"
                aria-labelledby="support-tab-guardian"
                tabindex="0"
                class="plans-section"
            >
                <div class="section-title">
                    <div>
                        <h2 id="plans-heading">运营守护者支持方案</h2>
                        <p>选择适合自己的方案，自愿支持，不自动扣款。</p>
                    </div>
                    <span class="payment-label">{{
                        support.payment_enabled
                            ? '微信支付'
                            : '微信收款暂未开放'
                    }}</span>
                </div>
                <div v-if="support.auth.is_supporter" class="member-note">
                    <Check :size="16" /><span
                        >你已是运营守护者<span
                            v-if="support.auth.supporter_until"
                            >，有效期至 {{ support.auth.supporter_until }}</span
                        ></span
                    >
                </div>
                <div class="plans">
                    <article
                        v-for="plan in support.plans"
                        :key="plan.id"
                        class="plan"
                        :class="{ chosen: selected === plan.id }"
                    >
                        <div class="plan-heading">
                            <h3>{{ plan.name }}</h3>
                            <span>{{ plan.badge_label }}</span>
                        </div>
                        <div class="price">{{ plan.amount_label }}</div>
                        <p class="duration">{{ plan.duration_label }}</p>
                        <ul>
                            <li v-for="benefit in plan.benefits" :key="benefit">
                                <Check :size="16" /><span>{{ benefit }}</span>
                            </li>
                        </ul>
                        <button
                            class="choose"
                            :disabled="!support.payment_enabled"
                            @click="selected = plan.id"
                        >
                            <span>{{
                                !support.payment_enabled
                                    ? '收款暂未开放'
                                    : selected === plan.id
                                      ? '已选择 · 仅预览'
                                      : '选择支持'
                            }}</span
                            ><ArrowUpRight :size="18" />
                        </button>
                    </article>
                </div>
                <p v-if="selected" role="status" class="preview-feedback">
                    已选择方案。此处仅演示样式，不创建订单或扣款。
                </p>
            </section>
            <section
                id="support-panel-contributor"
                v-show="activeTab === 'contributor'"
                class="contributor-panel"
                role="tabpanel"
                aria-labelledby="support-tab-contributor"
                tabindex="0"
            >
                <div class="contributor-copy">
                    <h2>用你的收藏，丰富这份档案</h2>
                    <p class="lead">
                        收藏了大量梅西高清图片，并愿意分享给本站？<br />欢迎联系管理员，一起整理和留存这些影像。
                    </p>
                    <p>
                        比赛组图、生涯旧照、高清素材，以及可靠的拍摄时间和来源信息，都能帮助档案更加完整。
                    </p>
                    <div class="contribution-credit">
                        <h3>让你的贡献，被长久记住</h3>
                        <p>
                            经沟通确认、授予档案共建者身份后，可自愿在致谢墙永久留名。昵称、头像及简介经你同意后展示，也可随时申请隐藏。
                        </p>
                    </div>
                    <p class="rights-note">
                        请仅分享你有权提供的资料，尽可能保留原始来源。共建身份不代表图片版权归属或转载授权。
                    </p>
                </div>
                <aside class="contact-admin">
                    <div id="admin-contact-qr" class="qr-frame">
                        <img
                            v-if="showContactQr"
                            :src="friendQr"
                            alt="联系网站管理员的微信二维码"
                            width="200"
                            height="200"
                        />
                        <div v-else class="qr-concealed">
                            <span
                                class="qr-placeholder"
                                aria-hidden="true"
                            ></span
                            ><button
                                type="button"
                                aria-controls="admin-contact-qr"
                                :aria-expanded="showContactQr"
                                @click="showContactQr = true"
                            >
                                点击查看二维码
                            </button>
                        </div>
                    </div>
                    <button
                        v-if="showContactQr"
                        type="button"
                        class="qr-hide"
                        aria-controls="admin-contact-qr"
                        :aria-expanded="showContactQr"
                        @click="showContactQr = false"
                    >
                        收起二维码
                    </button>
                    <h3>扫码联系管理员</h3>
                    <p>请注明「档案共建」<br />并简要介绍你的图片资源</p>
                    <small>添加好友请注明来意</small>
                </aside>
            </section>
            <div class="closing-grid reveal">
                <section
                    class="notices reveal"
                    aria-labelledby="notices-heading"
                >
                    <div class="section-title">
                        <h2 id="notices-heading">支持注意事项</h2>
                    </div>
                    <dl class="notice-list">
                        <div>
                            <dt>支持维护，不售卖版权</dt>
                            <dd>
                                赞助不解锁原图下载，也不赋予转载、商用或授权使用权。
                            </dd>
                        </div>
                        <div>
                            <dt>自愿赞助，不自动扣款</dt>
                            <dd>
                                仅限正常登录用户参与；退款请通过站点联系方式申请。
                            </dd>
                        </div>
                        <div>
                            <dt>公开展示，由你决定</dt>
                            <dd>可在个人中心决定是否展示在致谢墙。</dd>
                        </div>
                        <div>
                            <dt>关于运营结余</dt>
                            <dd>
                                结余将用于公益捐赠，相关信息会在条件允许时公开。
                            </dd>
                        </div>
                    </dl>
                </section>
                <aside class="thanks" aria-labelledby="thanks-heading">
                    <div>
                        <span class="thanks-label">公开致谢</span>
                        <h2 id="thanks-heading">每一份贡献，都值得被记住。</h2>
                        <p>感谢愿意公开昵称的运营守护者与档案共建者。</p>
                    </div>
                    <Link href="/supporters"
                        >查看致谢墙<ArrowUpRight :size="18"
                    /></Link>
                </aside>
            </div>
        </div>
    </main>
</template>
<style scoped>
.support-preview {
    --ink: #042240;
    --gold: #ba9f6c;
    color: var(--ink);
    background: #fff;
}
.page-shell {
    max-width: 1280px;
    margin: auto;
    padding: 38px 32px 48px;
}
.preview-note {
    font-size: 11px;
    color: #738496;
    display: block;
    margin-bottom: 12px;
}
h1 {
    font-size: 38px;
    font-weight: 750;
    letter-spacing: -0.035em;
    line-height: 1.25;
    margin: 0;
}
.intro > p {
    font-size: 15px;
    color: #536c82;
    line-height: 1.8;
    margin: 14px 0 0;
}
.intro {
    margin-bottom: 30px;
}
.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
}
h2 {
    font-size: 24px;
    line-height: 1.35;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin: 0;
}
.section-title p {
    font-size: 13px;
    color: #61788c;
    line-height: 1.7;
    margin: 9px 0 0;
}
.plans-section {
    margin-top: 36px;
    scroll-margin-top: 100px;
    padding: 30px 28px 28px;
    background: #f0f7fc;
    border-radius: 8px;
}
.payment-label {
    font-size: 12px;
    background: #fff;
    border: 1px solid #d8e5ed;
    padding: 7px 12px;
    border-radius: 4px;
    white-space: nowrap;
}
.member-note {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #5b7184;
    margin-bottom: 18px;
    line-height: 1.7;
}
.member-note svg {
    color: #927743;
    flex-shrink: 0;
}
.plans {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}
.plan {
    padding: 25px 24px;
    background: #fff;
    border: 1px solid #dce7ef;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    min-width: 0;
    transition:
        transform 0.23s,
        border-color 0.23s;
}
.plan:hover {
    transform: translateY(-3px);
    border-color: var(--gold);
}
.plan.chosen {
    border-color: #967b45;
}
.plan-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}
.plan-heading h3 {
    font-size: 19px;
    font-weight: 650;
    margin: 0;
}
.plan-heading > span {
    font-size: 10px;
    padding: 4px 7px;
    color: #826b41;
    background: #f7f2e9;
    border-radius: 3px;
    white-space: nowrap;
}
.price {
    font-size: 43px;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.045em;
    margin-top: 25px;
}
.duration {
    font-size: 12px;
    color: #60768a;
    margin: 9px 0 0;
    line-height: 1.7;
}
.plan ul {
    padding: 0;
    margin: 22px 0 25px;
    list-style: none;
    flex: 1;
}
.plan li {
    display: flex;
    gap: 8px;
    align-items: start;
    font-size: 12px;
    line-height: 1.8;
    color: #4f6579;
}
.plan li + li {
    margin-top: 7px;
}
.plan li svg {
    flex-shrink: 0;
    margin-top: 3px;
    color: #9b804f;
}
.choose {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 46px;
    padding: 12px 15px;
    background: var(--gold);
    color: var(--ink);
    font-size: 13px;
    font-weight: 650;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
}
.choose:hover {
    background: #c9b17e;
}
.choose:disabled {
    background: #e6edf1;
    color: #688094;
    cursor: not-allowed;
}
.preview-feedback {
    font-size: 12px;
    margin: 16px 0 0;
    line-height: 1.7;
}
.notices {
    margin-top: 36px;
}
.notice-list {
    margin: 0;
}
.notice-list > div {
    display: grid;
    grid-template-columns: 215px 1fr;
    gap: 24px;
    padding: 15px 0;
    border-bottom: 1px solid #e2eaf0;
    font-size: 13px;
    line-height: 1.8;
}
.notice-list dt {
    font-weight: 650;
}
.notice-list dd {
    margin: 0;
    color: #5b7184;
}
.thanks {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-top: 24px;
}
.thanks p {
    font-size: 13px;
    color: #61788c;
    margin: 0;
}
.thanks a {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    min-height: 44px;
    padding: 0 17px;
    border: 1px solid #cbdbe7;
    border-radius: 4px;
    color: var(--ink);
    font-size: 13px;
    font-weight: 650;
    text-decoration: none;
    transition: background 0.2s;
}
.thanks a:hover {
    background: #edf6fc;
}
a:focus-visible,
button:focus-visible {
    outline: 3px solid #967b45;
    outline-offset: 4px;
}
@media (max-width: 1000px) {
    .plan-heading {
        flex-direction: column;
        align-items: start;
        gap: 7px;
    }
    .plan {
        padding: 22px 18px;
    }
    .plans-section {
        padding: 26px 20px;
    }
}
@media (max-width: 640px) {
    .page-shell {
        padding: 26px 20px 32px;
    }
    h1 {
        font-size: 30px;
    }
    .intro p {
        font-size: 14px;
    }
    .intro {
        margin-bottom: 26px;
    }
    h2 {
        font-size: 22px;
    }

    .way {
        padding: 21px;
    }
    .way h3 {
        font-size: 19px;
    }
    .way p {
        font-size: 13px;
    }
    .plans-section {
        margin-top: 28px;
        margin-inline: -20px;
        border-radius: 0;
        padding: 26px 20px;
    }
    .section-title {
        gap: 12px;
        align-items: start;
    }
    .plans-section .section-title {
        flex-direction: column;
        gap: 10px;
    }
    .plans {
        grid-template-columns: 1fr;
        gap: 14px;
    }
    .plan {
        padding: 23px;
    }
    .plan-heading {
        flex-direction: row;
        align-items: center;
    }
    .price {
        margin-top: 20px;
        font-size: 40px;
    }
    .notices {
        margin-top: 28px;
    }
    .notice-list > div {
        grid-template-columns: 1fr;
        gap: 5px;
        padding: 14px 0;
    }
    .thanks {
        align-items: start;
        flex-direction: column;
        gap: 14px;
    }
    .thanks a {
        justify-content: space-between;
        width: 100%;
    }
    .member-note {
        align-items: start;
    }
}
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition: none !important;
    }
}

.role-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.role-tabs button {
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
    padding: 18px 22px;
    min-height: 78px;
    background: #f7fafc;
    color: var(--ink);
    border: 1px solid #d9e5ee;
    border-radius: 6px;
    cursor: pointer;
    transition:
        background 0.2s,
        border-color 0.2s;
}
.role-tabs button > span {
    flex: 1;
    font-size: 18px;
    font-weight: 650;
}
.role-tabs small {
    display: block;
    font-size: 12px;
    font-weight: 400;
    margin-top: 5px;
    color: #60778b;
}
.role-tabs button[aria-selected='true'] {
    background: #f5efe2;
    border-color: var(--gold);
}
.role-tabs button[aria-selected='true'] > svg {
    color: #927743;
}
.role-tabs button:hover {
    border-color: var(--gold);
}
.contributor-panel {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 52px;
    padding: 32px;
    background: #f0f7fc;
    border-radius: 8px;
    margin-top: 24px;
}
.contributor-copy p {
    font-size: 14px;
    line-height: 1.85;
    color: #536c82;
    max-width: 680px;
}
.contributor-copy .lead {
    font-size: 16px;
    margin: 18px 0 14px;
    color: var(--ink);
}
.contribution-credit {
    margin: 24px 0 16px;
}
.contribution-credit h3 {
    font-size: 17px;
    font-weight: 650;
}
.contributor-copy .rights-note {
    font-size: 12px;
    color: #6b7e90;
    margin-bottom: 0;
}
.contact-admin {
    text-align: center;
    background: white;
    padding: 22px 20px;
    border-radius: 6px;
    align-self: start;
}
.contact-admin img {
    display: block;
    width: 100%;
    max-width: 200px;
    height: auto;
    margin: 0 auto 14px;
}
.contact-admin h3 {
    font-size: 16px;
    font-weight: 650;
}
.contact-admin p {
    font-size: 13px;
    line-height: 1.8;
    margin: 10px 0;
    color: #536c82;
}
.contact-admin small {
    font-size: 11px;
    color: #6c7f90;
}
.plans-section {
    margin-top: 24px;
}
.closing-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr);
    gap: 28px;
    margin-top: 32px;
    align-items: stretch;
}
.closing-grid .notices {
    margin-top: 0;
}
.closing-grid .notice-list > div {
    grid-template-columns: 1fr;
    gap: 4px;
    padding: 12px 0;
}
.closing-grid .thanks {
    display: flex;
    flex-direction: column;
    align-items: start;
    justify-content: space-between;
    gap: 24px;
    background: #edf6fc;
    border-radius: 7px;
    padding: 28px;
    margin-top: 0;
}
.thanks-label {
    display: block;
    font-size: 12px;
    color: #7e673d;
    margin-bottom: 14px;
}
.closing-grid .thanks h2 {
    font-size: 25px;
    line-height: 1.5;
    max-width: 250px;
}
.closing-grid .thanks p {
    line-height: 1.8;
    margin-top: 14px;
}
.closing-grid .thanks a {
    background: var(--ink);
    color: white;
    border-color: var(--ink);
    min-width: 160px;
    justify-content: space-between;
}
.closing-grid .thanks a:hover {
    background: #164b70;
}
@media (max-width: 640px) {
    .role-tabs {
        gap: 10px;
    }
    .role-tabs button {
        padding: 14px 12px;
        gap: 8px;
        min-height: 76px;
        flex-wrap: wrap;
    }
    .role-tabs button > span {
        font-size: 16px;
    }
    .role-tabs small {
        font-size: 10px;
    }
    .role-tabs button > svg:last-child:not(:first-child) {
        display: none;
    }
    .contributor-panel {
        grid-template-columns: 1fr;
        gap: 24px;
        padding: 24px 20px;
        margin-inline: -20px;
        border-radius: 0;
    }
    .contact-admin {
        width: 250px;
        max-width: 100%;
        justify-self: center;
    }
    .contributor-copy .lead {
        font-size: 15px;
    }
    .closing-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    .closing-grid .thanks {
        padding: 24px;
    }
    .closing-grid .thanks h2 {
        max-width: none;
        font-size: 23px;
    }
    .closing-grid .thanks a {
        width: 100%;
    }
}

.qr-frame {
    width: 200px;
    max-width: 100%;
    aspect-ratio: 1;
    margin: 0 auto 14px;
    position: relative;
    overflow: hidden;
    border-radius: 5px;
    background: #edf3f7;
}
.qr-frame img {
    margin: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.qr-concealed {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
}
.qr-placeholder {
    position: absolute;
    inset: 16px;
    background: repeating-conic-gradient(#a9becf 0% 25%, #eef4f8 0% 50%) 0
        0/24px 24px;
    filter: blur(8px);
    opacity: 0.55;
}
.qr-concealed button {
    position: relative;
    min-height: 44px;
    padding: 10px 16px;
    background: #fffffff2;
    border: 1px solid #ccdce8;
    border-radius: 5px;
    color: var(--ink);
    font-size: 13px;
    font-weight: 650;
    cursor: pointer;
}
.qr-concealed button:hover {
    background: #f6f0e5;
    border-color: var(--gold);
}
.qr-hide {
    background: none;
    border: 0;
    min-height: 36px;
    padding: 4px 12px;
    color: #526e85;
    font-size: 12px;
    cursor: pointer;
    margin: -6px auto 8px;
}
</style>
