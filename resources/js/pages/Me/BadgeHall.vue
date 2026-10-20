<script setup lang="ts">
import BadgeIcon from '@/components/BadgeIcon.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Badge {
    id: number;
    name: string;
    description: string | null;
    color: string;
    image_url: string | null;
    rule_label: string;
    availability: string;
}
const props = defineProps<{
    hall: {
        site: {
            name: string;
            logo_url: string | null;
            search_placeholder: string;
        };
        navigation: { label: string; url: string }[];
        user: InstanceType<typeof UserCenterNav>['$props']['user'];
        summary: InstanceType<typeof UserCenterNav>['$props']['summary'];
        badges: Badge[];
    };
}>();
const search = ref('');
const badges = computed(() =>
    props.hall.badges.filter((badge) =>
        `${badge.name} ${badge.description ?? ''}`.includes(
            search.value.trim(),
        ),
    ),
);
</script>

<template>
    <Head title="勋章大厅" />
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="hall.navigation" :site="hall.site" />
        <section
            class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <h1 class="text-3xl font-semibold sm:text-4xl">勋章大厅</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-[#5f6c7b]">
                    记录每一份参与。了解勋章的获得条件，收藏属于自己的纪念。
                </p>
                <Link
                    href="/me/badges"
                    class="mt-5 inline-flex min-h-11 items-center rounded-sm bg-[#094067] px-4 text-sm font-semibold text-white"
                    >管理我的勋章</Link
                >
            </div>
        </section>
        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[280px_minmax(0,1fr)]"
            >
                <UserCenterNav
                    :user="hall.user"
                    :summary="hall.summary"
                    active="badge-hall"
                />
                <div class="min-w-0">
                    <div
                        class="mb-6 flex flex-wrap items-center justify-between gap-4"
                    >
                        <p class="text-sm text-[#5f6c7b]">
                            共 {{ hall.badges.length }} 枚勋章 · 获得后永久保留
                        </p>
                        <label class="flex items-center gap-3 text-sm"
                            ><span>查找勋章</span
                            ><input
                                v-model="search"
                                type="search"
                                class="min-h-11 w-48 rounded-sm border border-[#90b4ce] bg-white px-3 text-[#094067] focus:outline-2 focus:outline-[#3da9fc]"
                                placeholder="名称或说明"
                        /></label>
                    </div>
                    <div
                        v-if="badges.length"
                        class="grid gap-x-8 md:grid-cols-2"
                    >
                        <article
                            v-for="badge in badges"
                            :key="badge.id"
                            class="flex gap-4 border-b border-[#90b4ce]/30 py-6"
                        >
                            <BadgeIcon :badge="badge" />
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold break-words">
                                    {{ badge.name }}
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-6 text-[#5f6c7b]"
                                >
                                    {{ badge.description ?? badge.rule_label }}
                                </p>
                                <p class="mt-3 text-sm leading-6">
                                    {{ badge.availability }}
                                </p>
                            </div>
                        </article>
                    </div>
                    <p v-else class="py-12 text-center text-sm text-[#5f6c7b]">
                        {{
                            search
                                ? '没有找到匹配的勋章，请换个关键词。'
                                : '勋章正在准备中，开放后将在这里展示。'
                        }}
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
