<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ArrowUpRight, BookOpen, Check, Heart } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import '../../../css/archive-browser.css';
interface Person {
    id: number;
    avatar: string | null;
    display_name: string;
    badge_label?: string;
    last_supported_at?: string | null;
    bio?: string | null;
    contribution_focus?: string | null;
}
const props = defineProps<{
    supportersPage: {
        site: {
            name: string;
            logo_url: string | null;
            search_placeholder: string;
        };
        navigation: { label: string; url: string }[];
        seo: SeoPayload;
        supporters: {
            data: Person[];
            meta: { current_page: number; last_page: number; total: number };
            links: { prev: string | null; next: string | null };
        };
        contributors: { data: Person[] };
    };
}>();
const root = ref<HTMLElement | null>(null);
const tab = ref<'guardian' | 'contributor'>('guardian');
const failures = ref<Record<string, boolean>>({});
const people = computed(() =>
    tab.value === 'guardian'
        ? props.supportersPage.supporters.data
        : props.supportersPage.contributors.data,
);
let media: gsap.MatchMedia | undefined;
let tween: gsap.core.Tween | undefined;
const switchTab = async (value: 'guardian' | 'contributor', focus = false) => {
    if (value === tab.value) return;
    tween?.revert();
    tab.value = value;
    await nextTick();
    if (focus)
        root.value
            ?.querySelector<HTMLButtonElement>('#wall-tab-' + value)
            ?.focus();
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches)
        tween = gsap.fromTo(
            root.value?.querySelector('.people-panel'),
            { opacity: 0, y: 8 },
            {
                opacity: 1,
                y: 0,
                duration: 0.22,
                clearProps: 'opacity,transform',
            },
        );
};
onMounted(() => {
    media = gsap.matchMedia();
    media.add('(prefers-reduced-motion: no-preference)', () => {
        const ctx = gsap.context(
            () =>
                gsap.from('.wall-intro,.role-tabs,.people-panel,.wall-bottom', {
                    y: 12,
                    opacity: 0,
                    duration: 0.45,
                    stagger: 0.08,
                    clearProps: 'all',
                }),
            root.value!,
        );
        return () => ctx.revert();
    });
});
onUnmounted(() => {
    tween?.revert();
    media?.revert();
});
</script>
<template>
    <SeoHead :seo="supportersPage.seo" />
    <main ref="root" class="supporter-wall archive-browser">
        <PublicHeader
            :site="supportersPage.site"
            :navigation="supportersPage.navigation"
            theme="afa"
        />
        <div class="wall-shell">
            <header class="wall-intro">
                <div>
                    <h1>致谢墙</h1>
                    <p>感谢每一份支持，也感谢每一次认真整理。</p>
                </div>
                <Link href="/support" class="support-link"
                    >了解如何支持<ArrowUpRight :size="18"
                /></Link>
            </header>
            <div class="role-tabs" role="tablist" aria-label="选择致谢名单">
                <button
                    id="wall-tab-guardian"
                    role="tab"
                    :aria-selected="tab === 'guardian'"
                    :tabindex="tab === 'guardian' ? 0 : -1"
                    aria-controls="wall-people"
                    @click="switchTab('guardian')"
                    @keydown.right.prevent="switchTab('contributor', true)"
                    @keydown.left.prevent="switchTab('contributor', true)"
                >
                    <Heart :size="22" /><span
                        >运营守护者<small>支持档案库的持续维护</small></span
                    ><b>{{ supportersPage.supporters.meta.total }}</b>
                </button>
                <button
                    id="wall-tab-contributor"
                    role="tab"
                    :aria-selected="tab === 'contributor'"
                    :tabindex="tab === 'contributor' ? 0 : -1"
                    aria-controls="wall-people"
                    @click="switchTab('contributor')"
                    @keydown.right.prevent="switchTab('guardian', true)"
                    @keydown.left.prevent="switchTab('guardian', true)"
                >
                    <BookOpen :size="22" /><span
                        >档案共建者<small>分享资料，完善影像记录</small></span
                    ><b>{{ supportersPage.contributors.data.length }}</b>
                </button>
            </div>
            <section
                id="wall-people"
                class="people-panel"
                role="tabpanel"
                :aria-labelledby="'wall-tab-' + tab"
                tabindex="0"
            >
                <div class="list-heading">
                    <div>
                        <h2>
                            {{
                                tab === 'guardian'
                                    ? '感谢你们的守护'
                                    : '感谢你们的共建'
                            }}
                        </h2>
                        <p>
                            {{
                                tab === 'guardian'
                                    ? '每一份支持，都在帮助档案库继续运转。'
                                    : '每一份资料与考据，都让档案更加完整。'
                            }}
                        </p>
                    </div>
                    <span>仅展示自愿公开的成员</span>
                </div>
                <div
                    v-if="people.length"
                    class="people-grid"
                    :class="{ builders: tab === 'contributor' }"
                >
                    <article
                        v-for="person in people"
                        :key="tab + '-' + person.id"
                        class="person-card"
                    >
                        <div class="person-main">
                            <div class="avatar">
                                <img
                                    v-if="
                                        person.avatar &&
                                        !failures[tab + '-' + person.id]
                                    "
                                    :src="person.avatar"
                                    :alt="person.display_name"
                                    loading="lazy"
                                    @error="
                                        failures[tab + '-' + person.id] = true
                                    "
                                /><span v-else>{{
                                    person.display_name.slice(0, 1)
                                }}</span>
                            </div>
                            <div class="identity">
                                <h3>{{ person.display_name }}</h3>
                                <span class="member-badge"
                                    ><Check :size="12" />{{
                                        tab === 'guardian'
                                            ? person.badge_label
                                            : '档案共建者'
                                    }}</span
                                >
                            </div>
                        </div>
                        <template v-if="tab === 'contributor'"
                            ><p v-if="person.contribution_focus" class="focus">
                                {{ person.contribution_focus }}
                            </p>
                            <p v-if="person.bio" class="bio">
                                {{ person.bio }}
                            </p>
                            <p
                                v-if="!person.contribution_focus && !person.bio"
                                class="quiet"
                            >
                                参与影像资料的整理与共建
                            </p></template
                        >
                        <div
                            v-else-if="person.last_supported_at"
                            class="support-date"
                        >
                            <span>最近支持</span
                            ><time>{{ person.last_supported_at }}</time>
                        </div>
                    </article>
                </div>
                <div v-else class="empty">
                    <Heart v-if="tab === 'guardian'" :size="30" /><BookOpen
                        v-else
                        :size="30"
                    />
                    <h3>
                        暂未有公开展示的{{
                            tab === 'guardian' ? '运营守护者' : '档案共建者'
                        }}
                    </h3>
                    <p>名单会在成员同意公开后更新。</p>
                </div>
                <ArchivePagination
                    v-if="
                        tab === 'guardian' &&
                        supportersPage.supporters.meta.last_page > 1
                    "
                    :meta="supportersPage.supporters.meta"
                    :links="supportersPage.supporters.links"
                />
            </section>
            <div class="wall-bottom">
                <section class="display-note">
                    <h2>关于公开展示</h2>
                    <p>
                        仅展示成员同意公开的昵称、头像及简介，不展示邮箱、订单或支付明细。
                    </p>
                    <p>
                        运营守护者可在个人中心管理展示偏好；共建者可联系管理员调整或隐藏资料。
                    </p>
                </section>
                <aside class="join">
                    <h2>一起，让档案更完整。</h2>
                    <p>赞助日常维护，或分享你的高清图片资料。</p>
                    <Link href="/support"
                        >支持本站<ArrowUpRight :size="18"
                    /></Link>
                </aside>
            </div>
        </div>
    </main>
</template>
<style scoped>
.supporter-wall {
    --ink: #042240;
    --gold: #ba9f6c;
    color: var(--ink);
    background: #fff;
}
.wall-shell {
    max-width: 1280px;
    margin: auto;
    padding: 38px 32px 48px;
}
.wall-intro {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 24px;
    margin-bottom: 30px;
}
h1 {
    font-size: 38px;
    font-weight: 750;
    line-height: 1.2;
    letter-spacing: -0.035em;
    margin: 0;
}
.wall-intro p {
    font-size: 15px;
    color: #536c82;
    margin: 14px 0 0;
    line-height: 1.8;
}
.support-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 0 16px;
    min-height: 44px;
    border: 1px solid #d4e2eb;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 650;
    color: var(--ink);
    text-decoration: none;
    white-space: nowrap;
}
.support-link:hover {
    background: #edf6fc;
}
.role-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.role-tabs button {
    display: flex;
    align-items: center;
    gap: 13px;
    text-align: left;
    min-height: 80px;
    padding: 17px 22px;
    background: #f7fafc;
    border: 1px solid #d9e5ee;
    border-radius: 6px;
    color: var(--ink);
    cursor: pointer;
    transition:
        background 0.2s,
        border-color 0.2s;
}
.role-tabs button > span {
    font-size: 18px;
    font-weight: 650;
    flex: 1;
}
.role-tabs small {
    display: block;
    font-size: 12px;
    color: #60778b;
    font-weight: 400;
    margin-top: 5px;
}
.role-tabs b {
    font-size: 15px;
    font-weight: 650;
    min-width: 29px;
    height: 29px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #fff;
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
.people-panel {
    padding: 28px;
    background: #f0f7fc;
    border-radius: 8px;
    margin-top: 24px;
    min-height: 310px;
}
.list-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 22px;
}
h2 {
    font-size: 23px;
    line-height: 1.4;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin: 0;
}
.list-heading p {
    font-size: 13px;
    color: #61788c;
    margin: 8px 0 0;
    line-height: 1.8;
}
.list-heading > span {
    font-size: 11px;
    color: #6c7f90;
    white-space: nowrap;
}
.people-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.person-card {
    min-width: 0;
    padding: 24px;
    background: #fff;
    border: 1px solid #dce7ee;
    border-radius: 6px;
    transition:
        transform 0.22s,
        border-color 0.22s;
}
.person-card:hover {
    transform: translateY(-3px);
    border-color: var(--gold);
}
.person-main {
    display: flex;
    align-items: center;
    gap: 14px;
}
.avatar {
    width: 62px;
    height: 62px;
    flex-shrink: 0;
    border-radius: 50%;
    overflow: hidden;
    display: grid;
    place-items: center;
    background: #edf4f9;
    border: 1px solid #dce7ef;
    color: #587a95;
    font-size: 23px;
    font-weight: 600;
}
.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.identity {
    min-width: 0;
}
h3 {
    font-size: 16px;
    font-weight: 650;
    line-height: 1.6;
    margin: 0;
    overflow-wrap: anywhere;
}
.member-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f6f1e6;
    color: #816a40;
    font-size: 10px;
    padding: 4px 7px;
    border-radius: 3px;
    margin-top: 7px;
}
.support-date {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 11px;
    color: #6b8093;
    margin-top: 22px;
    padding-top: 14px;
    border-top: 1px solid #edf1f5;
}
.builders {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.builders .person-card {
    padding: 28px;
}
.builders .avatar {
    width: 70px;
    height: 70px;
}
.focus {
    font-size: 13px;
    color: #826b40;
    margin: 20px 0 0;
    line-height: 1.7;
}
.bio,
.quiet {
    font-size: 13px;
    color: #60778a;
    margin: 12px 0 0;
    line-height: 1.8;
    overflow-wrap: anywhere;
}
.wall-bottom {
    display: grid;
    grid-template-columns: 1.65fr 1fr;
    gap: 28px;
    margin-top: 30px;
    align-items: stretch;
}
.display-note {
    padding: 5px 0;
}
.display-note h2 {
    font-size: 20px;
}
.display-note p {
    font-size: 13px;
    color: #5f7689;
    line-height: 1.9;
    max-width: 610px;
    margin: 13px 0 0;
}
.join {
    padding: 26px;
    background: #edf6fc;
    border-radius: 6px;
}
.join h2 {
    font-size: 22px;
}
.join p {
    font-size: 13px;
    color: #60788c;
    line-height: 1.8;
    margin: 12px 0 18px;
}
.join a {
    min-height: 44px;
    padding: 0 15px;
    background: var(--ink);
    border-radius: 4px;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 35px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 650;
}
.join a:hover {
    background: #164b70;
}
.empty {
    padding: 44px 20px;
    text-align: center;
    color: #61788c;
}
.empty svg {
    color: #a88c54;
    margin: 0 auto 16px;
}
.empty h3 {
    color: var(--ink);
}
.empty p {
    font-size: 13px;
    margin: 10px 0;
}
a:focus-visible,
button:focus-visible {
    outline: 3px solid #967b45;
    outline-offset: 4px;
}
@media (max-width: 1000px) {
    .people-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .person-card {
        padding: 22px;
    }
    .person-main {
        gap: 10px;
    }
    .avatar {
        width: 52px;
        height: 52px;
    }
    .list-heading {
        align-items: start;
    }
}
@media (max-width: 640px) {
    .wall-shell {
        padding: 26px 20px 32px;
    }
    .wall-intro {
        align-items: start;
        gap: 10px;
    }
    h1 {
        font-size: 30px;
    }
    .wall-intro p {
        font-size: 13px;
        max-width: 210px;
    }
    .support-link {
        font-size: 12px;
        gap: 6px;
        padding: 0 10px;
        margin-top: 25px;
    }
    .role-tabs {
        gap: 10px;
    }
    .role-tabs button {
        padding: 15px 11px;
        gap: 7px;
    }
    .role-tabs button > span {
        font-size: 15px;
    }
    .role-tabs small {
        font-size: 10px;
    }
    .role-tabs b {
        font-size: 12px;
        min-width: 23px;
        height: 23px;
    }
    .role-tabs button > svg {
        width: 18px;
    }
    .people-panel {
        margin: 20px -20px 0;
        padding: 24px 20px;
        border-radius: 0;
    }
    .list-heading {
        display: block;
    }
    .list-heading > span {
        display: block;
        margin-top: 9px;
    }
    .list-heading h2 {
        font-size: 21px;
    }
    .people-grid,
    .builders {
        grid-template-columns: 1fr;
    }
    .person-card,
    .builders .person-card {
        padding: 22px;
    }
    .avatar {
        width: 58px;
        height: 58px;
    }
    .builders .avatar {
        width: 64px;
        height: 64px;
    }
    .wall-bottom {
        grid-template-columns: 1fr;
        gap: 24px;
        margin-top: 26px;
    }
    .join {
        padding: 24px;
    }
    .join a {
        width: 100%;
        justify-content: space-between;
    }
    .display-note h2 {
        font-size: 20px;
    }
}
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition: none !important;
    }
}
</style>
