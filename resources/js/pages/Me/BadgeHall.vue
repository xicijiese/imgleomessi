<script setup lang="ts">
import BadgeIcon from '@/components/BadgeIcon.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
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
const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
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
    <div ref="root" class="hall-page">
        <PublicHeader
            :navigation="hall.navigation"
            :site="hall.site"
            theme="afa"
        />
        <div class="hall-workspace">
            <UserCenterNav
                :user="hall.user"
                :summary="hall.summary"
                active="badge-hall"
                variant="archive"
            />
            <main class="hall-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>勋章大厅</h1>
                        <p>了解勋章的获得条件，收藏属于自己的纪念。</p>
                    </div>
                    <Link class="secondary" href="/me/badges"
                        >管理我的勋章</Link
                    >
                </div>
                <div class="hall-tools">
                    <p>共 {{ hall.badges.length }} 枚勋章 · 获得后永久保留</p>
                    <div class="hall-search">
                        <label for="hall-search">查找勋章</label
                        ><input
                            id="hall-search"
                            v-model="search"
                            type="search"
                            placeholder="名称或说明"
                        />
                    </div>
                </div>
                <p id="result-count" role="status">
                    {{
                        search.trim()
                            ? `找到 ${badges.length} 枚勋章`
                            : `展示全部 ${badges.length} 枚勋章`
                    }}
                </p>
                <section
                    v-if="badges.length"
                    class="hall-collection"
                    aria-label="勋章目录"
                >
                    <article
                        v-for="badge in badges"
                        :key="badge.id"
                        class="hall-card"
                    >
                        <div class="badge-overview">
                            <BadgeIcon :badge="badge" />
                            <div>
                                <h2>{{ badge.name }}</h2>
                            </div>
                        </div>
                        <p class="badge-description">
                            {{ badge.description ?? badge.rule_label }}
                        </p>
                        <dl class="hall-condition">
                            <dt>获取条件</dt>
                            <dd>{{ badge.availability }}</dd>
                        </dl>
                    </article>
                </section>
                <div v-else class="hall-empty">
                    <h2>
                        {{ search ? '没有找到匹配的勋章' : '勋章正在准备中' }}
                    </h2>
                    <p>
                        {{ search ? '请换个关键词。' : '开放后将在这里展示。' }}
                    </p>
                </div>
            </main>
        </div>
    </div>
</template>
<style scoped src="./BadgeHall.css"></style>
