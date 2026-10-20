<script setup lang="ts">
import BadgeIcon from '@/components/BadgeIcon.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
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
    router.patch(
        `/me/badges/${badge.id}/equip`,
        { equipped: !badge.is_equipped },
        {
            preserveScroll: true,
            onFinish: () => {
                pending.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="我的勋章" />
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="me.navigation" :site="me.site" />
        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <h1 class="text-3xl font-semibold sm:text-4xl">我的勋章</h1>
                <p class="mt-4 text-sm leading-7 text-[#5f6c7b]">
                    获得后永久保留。佩戴由你选择，可以同时佩戴多枚，不限数量。
                </p>
                <Link
                    href="/me/badges/hall"
                    class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold underline underline-offset-4"
                    >浏览勋章大厅</Link
                >
            </div>
        </section>
        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[280px_minmax(0,1fr)]"
            >
                <UserCenterNav
                    :user="me.user"
                    :summary="me.summary"
                    active="badges"
                />
                <div class="min-w-0 space-y-8">
                    <section>
                        <h2 class="text-xl font-semibold">
                            当前佩戴 · {{ me.equipped_badges.length }} 枚
                        </h2>
                        <div
                            v-if="me.equipped_badges.length"
                            class="mt-4 flex flex-wrap gap-4"
                        >
                            <div
                                v-for="badge in me.equipped_badges"
                                :key="badge.id"
                                class="flex items-center gap-2"
                            >
                                <BadgeIcon :badge="badge" /><span
                                    class="text-sm"
                                    >{{ badge.name }}</span
                                >
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm text-[#5f6c7b]">
                            尚未佩戴勋章，可在下方选择已获得的勋章。
                        </p>
                    </section>
                    <section>
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b border-[#90b4ce]/35 pb-4"
                        >
                            <h2 class="text-xl font-semibold">勋章收藏</h2>
                            <label class="text-sm"
                                >筛选
                                <select
                                    v-model="filter"
                                    class="ml-2 min-h-11 rounded-sm border border-[#90b4ce] bg-white px-3"
                                >
                                    <option value="all">全部勋章</option>
                                    <option value="earned">已获得</option>
                                    <option value="equipped">正在佩戴</option>
                                </select>
                            </label>
                        </div>
                        <div
                            v-if="visibleBadges.length"
                            class="grid gap-x-8 md:grid-cols-2"
                        >
                            <article
                                v-for="badge in visibleBadges"
                                :key="badge.id"
                                class="border-b border-[#90b4ce]/30 py-6"
                            >
                                <div class="flex items-start gap-4">
                                    <BadgeIcon :badge="badge" />
                                    <div class="min-w-0 flex-1">
                                        <h3
                                            class="text-lg font-semibold break-words"
                                        >
                                            {{ badge.name }}
                                        </h3>
                                        <p class="mt-1 text-xs text-[#5f6c7b]">
                                            {{
                                                badge.is_earned
                                                    ? `已获得 · ${badge.earned_at ?? ''}`
                                                    : '未获得'
                                            }}
                                        </p>
                                        <p
                                            class="mt-3 text-sm leading-6 text-[#5f6c7b]"
                                        >
                                            {{
                                                badge.description ??
                                                badge.rule_label
                                            }}
                                        </p>
                                        <p class="mt-2 text-sm leading-6">
                                            {{ badge.availability }}
                                        </p>
                                        <div
                                            v-if="
                                                badge.progress_target &&
                                                !badge.is_earned
                                            "
                                            class="mt-3"
                                        >
                                            <p class="text-xs text-[#5f6c7b]">
                                                进度
                                                {{ badge.progress_current }} /
                                                {{ badge.progress_target }}
                                            </p>
                                            <progress
                                                class="mt-1 h-2 w-full accent-[#3da9fc]"
                                                :value="badge.progress_percent"
                                                max="100"
                                                :aria-label="`${badge.name}完成进度`"
                                            />
                                        </div>
                                        <button
                                            v-if="badge.is_earned"
                                            type="button"
                                            :disabled="
                                                pending !== null ||
                                                me.user.is_banned
                                            "
                                            class="mt-4 min-h-11 rounded-sm border border-[#094067] px-4 text-sm font-semibold transition hover:bg-[#d8eefe] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-wait disabled:opacity-50"
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
                                    </div>
                                </div>
                            </article>
                        </div>
                        <p v-else class="py-10 text-sm text-[#5f6c7b]">
                            这里还没有勋章，可以切换筛选或前往勋章大厅了解获取条件。
                        </p>
                    </section>
                </div>
            </div>
        </section>
    </main>
</template>
