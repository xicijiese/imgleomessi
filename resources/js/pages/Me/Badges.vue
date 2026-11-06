<script setup lang="ts">
import BadgeIcon from '@/components/BadgeIcon.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

interface BadgeItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon_key: string | null;
    color: string;
    image_url: string | null;
    availability: string;
    rule_type: string;
    rule_label: string;
    rule_threshold: number | null;
    is_earned: boolean;
    is_equipped: boolean;
    earned_at: string | null;
    progress_current: number;
    progress_target: number | null;
    progress_percent: number;
}

interface MePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    user: UserPayload;
    summary: SummaryPayload;
    equipped_badges: BadgeItem[];
    badges: BadgeItem[];
}

const props = defineProps<{
    me: MePayload;
}>();

const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
const feedback = ref<string | null>(null);
const feedbackError = ref(false);
const pending = ref<number | null>(null);
const filter = ref('all');
const visibleBadges = computed(() =>
    props.me.badges.filter(
        (badge) =>
            filter.value === 'all' ||
            (filter.value === 'earned' ? badge.is_earned : badge.is_equipped),
    ),
);
const equipBadge = (badge: BadgeItem) => {
    if (pending.value !== null) return;
    pending.value = badge.id;
    feedback.value = null;
    router.patch(
        `/me/badges/${badge.id}/equip`,
        { equipped: !badge.is_equipped },
        {
            preserveScroll: true,
            onSuccess: () => {
                feedbackError.value = false;
                feedback.value = '佩戴状态已更新。';
            },
            onError: () => {
                feedbackError.value = true;
                feedback.value = '保存失败，请稍后重试。';
            },
            onFinish: () => {
                pending.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="我的勋章" />
    <div ref="root" class="badges-page">
        <PublicHeader :navigation="me.navigation" :site="me.site" theme="afa" />
        <div class="badges-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="badges"
                variant="archive"
            />
            <main class="badges-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>我的勋章</h1>
                        <p>获得后永久保留。可同时佩戴多枚，不限数量。</p>
                    </div>
                    <Link class="secondary" href="/me/badges/hall"
                        >浏览勋章大厅</Link
                    >
                </div>
                <section
                    class="equipped-panel"
                    aria-labelledby="equipped-title"
                >
                    <h2 id="equipped-title">
                        当前佩戴 · {{ me.equipped_badges.length }} 枚
                    </h2>
                    <div v-if="me.equipped_badges.length" class="equipped-list">
                        <div
                            v-for="badge in me.equipped_badges"
                            :key="badge.id"
                            class="worn-badge"
                        >
                            <BadgeIcon :badge="badge" /><span>{{
                                badge.name
                            }}</span>
                        </div>
                    </div>
                    <p v-else class="empty-equipped">
                        尚未佩戴勋章，可在下方选择已获得的勋章。
                    </p>
                </section>
                <div class="collection-heading">
                    <h2>勋章收藏</h2>
                    <div>
                        <label for="badge-filter">筛选</label
                        ><select id="badge-filter" v-model="filter">
                            <option value="all">全部勋章</option>
                            <option value="earned">已获得</option>
                            <option value="equipped">正在佩戴</option>
                        </select>
                    </div>
                </div>
                <p
                    v-if="feedback"
                    id="feedback"
                    :role="feedbackError ? 'alert' : 'status'"
                >
                    {{ feedback }}
                </p>
                <section
                    v-if="visibleBadges.length"
                    id="badge-collection"
                    aria-label="勋章收藏"
                >
                    <article
                        v-for="badge in visibleBadges"
                        :key="badge.id"
                        class="badge-card"
                    >
                        <div class="badge-overview">
                            <BadgeIcon :badge="badge" />
                            <div>
                                <h3>{{ badge.name }}</h3>
                                <p class="earned-state">
                                    {{
                                        badge.is_earned
                                            ? `已获得 · ${badge.earned_at ?? ''}`
                                            : '未获得'
                                    }}<span v-if="badge.is_equipped">
                                        · 正在佩戴</span
                                    >
                                </p>
                            </div>
                        </div>
                        <p class="badge-description">
                            {{ badge.description ?? badge.rule_label }}
                        </p>
                        <p class="availability">{{ badge.availability }}</p>
                        <div
                            v-if="badge.progress_target && !badge.is_earned"
                            class="badge-progress"
                        >
                            <p>
                                进度 {{ badge.progress_current }} /
                                {{ badge.progress_target }}
                            </p>
                            <progress
                                :value="badge.progress_percent"
                                max="100"
                                :aria-label="`${badge.name}完成进度`"
                            />
                        </div>
                        <button
                            v-if="badge.is_earned"
                            class="equip-action"
                            :class="{ 'equipped-action': badge.is_equipped }"
                            type="button"
                            :disabled="pending !== null || me.user.is_banned"
                            :aria-pressed="badge.is_equipped"
                            @click="equipBadge(badge)"
                        >
                            {{
                                pending === badge.id
                                    ? '保存中…'
                                    : badge.is_equipped
                                      ? '取消佩戴'
                                      : '佩戴勋章'
                            }}
                        </button>
                    </article>
                </section>
                <div v-else class="records-empty">
                    <h3>这里还没有勋章</h3>
                    <p>可以切换筛选或前往勋章大厅了解获取条件。</p>
                </div>
            </main>
        </div>
    </div>
</template>
<style scoped src="./Badges.css"></style>
