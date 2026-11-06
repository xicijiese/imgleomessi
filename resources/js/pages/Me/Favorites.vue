<script setup lang="ts">
import ArchivePagination from '@/components/ArchivePagination.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';

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
interface CategoryOption {
    id: number;
    name: string;
    group: string | null;
}
interface PhotoSummary {
    is_available: boolean;
    title: string;
    url: string | null;
    image_url: string | null;
    category_summary: string | null;
}
interface FavoriteItem {
    id: number;
    created_at: string | null;
    photo: PhotoSummary;
}
interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: { prev: string | null; next: string | null };
}
interface MePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    user: UserPayload;
    summary: SummaryPayload;
    filters: {
        q: string;
        category_id: number | null;
        date_from: string;
        date_to: string;
    };
    filter_options: { categories: CategoryOption[] };
    favorites: Paginated<FavoriteItem>;
}

const props = defineProps<{ me: MePayload }>();
const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
const filtersOpen = ref(false);
let desktop: MediaQueryList | undefined;
const syncFilters = () => {
    filtersOpen.value = desktop?.matches ?? false;
};
onMounted(() => {
    desktop = window.matchMedia('(min-width: 768px)');
    syncFilters();
    desktop.addEventListener('change', syncFilters);
});
onUnmounted(() => desktop?.removeEventListener('change', syncFilters));
const appliedFilterCount = computed(
    () => Object.values(props.me.filters).filter(Boolean).length,
);
const isFirstEmpty = computed(
    () =>
        props.me.summary.favorites_count === 0 &&
        appliedFilterCount.value === 0,
);
const favorites = ref([...props.me.favorites.data]);
const removing = ref<Record<number, boolean>>({});
const status = ref<string | null>(null);
const form = reactive({
    q: props.me.filters.q,
    category_id: props.me.filters.category_id
        ? String(props.me.filters.category_id)
        : '',
    date_from: props.me.filters.date_from,
    date_to: props.me.filters.date_to,
});

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const applyFilters = () => {
    router.get(
        '/me/favorites',
        {
            q: form.q.trim() || undefined,
            category_id: form.category_id || undefined,
            date_from: form.date_from || undefined,
            date_to: form.date_to || undefined,
        },
        { preserveScroll: false, preserveState: false, replace: true },
    );
};
const resetFilters = () =>
    router.get(
        '/me/favorites',
        {},
        { preserveScroll: false, preserveState: false, replace: true },
    );
const removeFavorite = async (favorite: FavoriteItem) => {
    if (removing.value[favorite.id]) return;
    const activeElement = document.activeElement;
    const restoreFocus =
        activeElement instanceof HTMLElement &&
        activeElement.dataset.favoriteId === String(favorite.id);
    removing.value = { ...removing.value, [favorite.id]: true };
    try {
        const response = await fetch('/me/favorites/' + favorite.id, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) throw new Error('remove_failed');
        const removedIndex = favorites.value.findIndex(
            (item) => item.id === favorite.id,
        );
        favorites.value = favorites.value.filter(
            (item) => item.id !== favorite.id,
        );
        status.value = '已取消收藏。';
        await nextTick();
        if (
            restoreFocus &&
            (document.activeElement === activeElement ||
                document.activeElement === document.body)
        ) {
            const buttons = root.value?.querySelectorAll<HTMLButtonElement>(
                '.remove-favorite:not(:disabled)',
            );
            const target =
                buttons?.[Math.min(removedIndex, buttons.length - 1)] ??
                root.value?.querySelector<HTMLElement>(
                    '.favorites-empty a, .favorites-empty button',
                );
            target?.focus();
        }
    } catch {
        status.value = '取消收藏失败，请稍后重试。';
    } finally {
        removing.value = { ...removing.value, [favorite.id]: false };
        window.setTimeout(() => (status.value = null), 1800);
    }
};
</script>

<template>
    <Head title="我的收藏" />
    <div ref="root" class="favorites-page">
        <PublicHeader :navigation="me.navigation" :site="me.site" theme="afa" />
        <div class="favorites-workspace">
            <UserCenterNav
                :user="me.user"
                :summary="me.summary"
                active="favorites"
                variant="archive"
            />
            <main class="favorites-work">
                <div class="page-heading browser-heading">
                    <div>
                        <h1>我的收藏</h1>
                        <p>共 {{ me.favorites.meta.total }} 条收藏记录</p>
                    </div>
                </div>
                <form class="favorites-filters" @submit.prevent="applyFilters">
                    <div class="keyword-row">
                        <label for="favorite-keyword">搜索收藏图片</label>
                        <div class="keyword-control">
                            <input
                                id="favorite-keyword"
                                v-model="form.q"
                                type="search"
                                name="q"
                                placeholder="输入图片标题"
                            /><button
                                class="filter-submit keyword-submit"
                                type="submit"
                            >
                                筛选
                            </button>
                        </div>
                    </div>
                    <details
                        id="filter-details"
                        :open="filtersOpen"
                        @toggle="
                            filtersOpen = ($event.target as HTMLDetailsElement)
                                .open
                        "
                    >
                        <summary>
                            <span>筛选条件</span
                            ><span class="filter-count">{{
                                appliedFilterCount
                                    ? `已应用 ${appliedFilterCount} 项条件`
                                    : '分类与收藏日期'
                            }}</span
                            ><span class="filters-closed">展开</span
                            ><span class="filters-opened">收起</span>
                        </summary>
                        <div class="filter-fields">
                            <div class="filter-field">
                                <label for="favorite-category">图片分类</label
                                ><select
                                    id="favorite-category"
                                    v-model="form.category_id"
                                    name="category_id"
                                >
                                    <option value="">全部分类</option>
                                    <option
                                        v-for="category in me.filter_options
                                            .categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{
                                            category.group
                                                ? category.group +
                                                  ' / ' +
                                                  category.name
                                                : category.name
                                        }}
                                    </option>
                                </select>
                            </div>
                            <div class="filter-field">
                                <label for="favorite-date-from"
                                    >收藏开始日期</label
                                ><input
                                    id="favorite-date-from"
                                    v-model="form.date_from"
                                    name="date_from"
                                    type="date"
                                />
                            </div>
                            <div class="filter-field">
                                <label for="favorite-date-to"
                                    >收藏结束日期</label
                                ><input
                                    id="favorite-date-to"
                                    v-model="form.date_to"
                                    name="date_to"
                                    type="date"
                                />
                            </div>
                            <div class="filter-actions">
                                <button class="filter-submit" type="submit">
                                    应用筛选</button
                                ><button
                                    class="filter-reset"
                                    type="button"
                                    @click="resetFilters"
                                >
                                    清空条件
                                </button>
                            </div>
                        </div>
                    </details>
                </form>
                <div class="results-row">
                    <p>
                        {{
                            favorites.length
                                ? `本页 ${favorites.length} 条 · 第 ${me.favorites.meta.current_page} / ${me.favorites.meta.last_page} 页`
                                : '本页暂无记录'
                        }}
                    </p>
                    <p>按收藏时间倒序</p>
                </div>
                <p
                    v-if="status"
                    id="feedback"
                    :role="
                        status.startsWith('取消收藏失败') ? 'alert' : 'status'
                    "
                >
                    {{ status }}
                </p>
                <section aria-label="收藏图片">
                    <div v-if="favorites.length" class="favorites-grid">
                        <article
                            v-for="favorite in favorites"
                            :key="favorite.id"
                            class="favorite-card"
                        >
                            <Link
                                class="favorite-picture"
                                :href="favorite.photo.url ?? '/me/favorites'"
                                :aria-label="`查看图片：${favorite.photo.title}`"
                                ><img
                                    v-if="favorite.photo.image_url"
                                    :src="favorite.photo.image_url"
                                    :alt="favorite.photo.title"
                                    loading="lazy"
                                /><span v-else>图片暂不可见</span></Link
                            >
                            <div class="favorite-copy">
                                <h2 :title="favorite.photo.title">
                                    {{ favorite.photo.title }}
                                </h2>
                                <p
                                    class="favorite-category"
                                    :title="
                                        favorite.photo.category_summary ??
                                        '分类暂不可见'
                                    "
                                >
                                    {{
                                        favorite.photo.category_summary ??
                                        '分类暂不可见'
                                    }}
                                </p>
                                <p class="favorite-date">
                                    收藏于 {{ favorite.created_at }}
                                </p>
                                <button
                                    class="remove-favorite"
                                    type="button"
                                    :data-favorite-id="favorite.id"
                                    :aria-label="`取消收藏：${favorite.photo.title}`"
                                    :disabled="removing[favorite.id]"
                                    :aria-busy="Boolean(removing[favorite.id])"
                                    @click="removeFavorite(favorite)"
                                >
                                    {{
                                        removing[favorite.id]
                                            ? '处理中…'
                                            : '取消收藏'
                                    }}
                                </button>
                            </div>
                        </article>
                    </div>
                    <div v-else class="favorites-empty">
                        <h2>
                            {{
                                isFirstEmpty
                                    ? '还没有收藏图片'
                                    : '没有符合条件的收藏记录'
                            }}
                        </h2>
                        <p>
                            {{
                                isFirstEmpty
                                    ? '在图库中找到喜欢的影像后，可以将它加入收藏。'
                                    : '可以调整关键词、分类或收藏日期，再次查找。'
                            }}
                        </p>
                        <div class="empty-actions">
                            <Link
                                v-if="isFirstEmpty"
                                href="/photos"
                                class="secondary"
                                >浏览图库</Link
                            ><button
                                v-else
                                class="filter-reset"
                                type="button"
                                @click="resetFilters"
                            >
                                清空筛选条件
                            </button>
                        </div>
                    </div>
                </section>
                <ArchivePagination
                    :meta="me.favorites.meta"
                    :links="me.favorites.links"
                    label="收藏分页"
                />
            </main>
        </div>
    </div>
</template>

<style scoped src="./Favorites.css"></style>
