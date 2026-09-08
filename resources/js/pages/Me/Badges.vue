<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, router } from '@inertiajs/vue3';
import { BadgeCheck, CheckCircle2, Medal } from 'lucide-vue-next';

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
    equipped_badge: BadgeItem | null;
    badges: BadgeItem[];
}

defineProps<{
    me: MePayload;
}>();

const equipBadge = (badge: BadgeItem) => {
    router.patch(
        `/me/badges/${badge.id}/equip`,
        {},
        {
            preserveScroll: true,
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
                <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Badges</p>
                <h1 class="text-4xl font-semibold sm:text-5xl">我的勋章</h1>
                <p
                    class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b] sm:text-base"
                >
                    查看已经获得的成就、当前佩戴的勋章，以及后续可以解锁的目标。
                </p>
            </div>
        </section>

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[280px_minmax(0,1fr)]"
            >
                <UserCenterNav
                    :user="me.user"
                    :summary="me.summary"
                    active="badges"
                />

                <div class="space-y-6">
                    <section
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-sm text-[#fffffe]"
                                :style="{
                                    backgroundColor:
                                        me.equipped_badge?.color ?? '#90b4ce',
                                }"
                            >
                                <Medal class="h-7 w-7" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold">
                                    当前佩戴
                                </h2>
                                <p
                                    v-if="me.equipped_badge"
                                    class="mt-2 text-sm leading-6 text-[#5f6c7b]"
                                >
                                    {{ me.equipped_badge.name }} ·
                                    {{ me.equipped_badge.rule_label }}
                                </p>
                                <p
                                    v-else
                                    class="mt-2 text-sm leading-6 text-[#5f6c7b]"
                                >
                                    暂无佩戴勋章，获得任意勋章后会自动佩戴第一枚。
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-5 shadow-sm"
                    >
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-semibold">
                                    成就列表
                                </h2>
                                <p class="mt-1 text-sm text-[#5f6c7b]">
                                    P1 阶段只展示基础规则和人工发放结果。
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="badge in me.badges"
                                :key="badge.id"
                                class="rounded-sm border p-5 shadow-sm transition"
                                :class="
                                    badge.is_earned
                                        ? 'border-[#3da9fc]/45 bg-[#f7fbff]'
                                        : 'border-[#90b4ce]/35 bg-[#fffffe] opacity-85'
                                "
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-sm text-[#fffffe]"
                                        :style="{ backgroundColor: badge.color }"
                                    >
                                        <BadgeCheck
                                            class="h-6 w-6"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="
                                            badge.is_earned
                                                ? 'bg-[#3da9fc] text-[#fffffe]'
                                                : 'bg-[#d8eefe] text-[#094067]'
                                        "
                                    >
                                        {{ badge.is_earned ? '已获得' : '未获得' }}
                                    </span>
                                </div>

                                <h3 class="mt-4 text-lg font-semibold">
                                    {{ badge.name }}
                                </h3>
                                <p class="mt-2 min-h-12 text-sm leading-6 text-[#5f6c7b]">
                                    {{ badge.description ?? '暂无说明' }}
                                </p>

                                <div class="mt-4 space-y-2">
                                    <div
                                        class="flex items-center justify-between text-xs font-semibold text-[#5f6c7b]"
                                    >
                                        <span>{{ badge.rule_label }}</span>
                                        <span v-if="badge.progress_target">
                                            {{ badge.progress_current }} /
                                            {{ badge.progress_target }}
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-[#d8eefe]"
                                    >
                                        <div
                                            class="h-full rounded-full bg-[#3da9fc]"
                                            :style="{
                                                width: `${badge.progress_percent}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>

                                <div class="mt-5 flex min-h-10 items-center gap-3">
                                    <span
                                        v-if="badge.is_equipped"
                                        class="inline-flex h-10 items-center gap-2 rounded-sm bg-[#094067] px-3 text-sm font-semibold text-[#fffffe]"
                                    >
                                        <CheckCircle2
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        正在佩戴
                                    </span>
                                    <button
                                        v-else-if="badge.is_earned"
                                        type="button"
                                        class="inline-flex h-10 items-center justify-center rounded-sm border border-[#3da9fc] px-3 text-sm font-semibold text-[#094067] transition hover:bg-[#d8eefe]"
                                        @click="equipBadge(badge)"
                                    >
                                        佩戴
                                    </button>
                                    <span v-else class="text-sm text-[#5f6c7b]">
                                        达成条件后自动获得
                                    </span>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </main>
</template>