<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import UserCenterNav from '@/components/UserCenterNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff, Trash2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface NavigationItem { label: string; url: string; }
interface SitePayload { name: string; logo_url: string | null; search_placeholder: string; }
interface SummaryPayload { favorites_count: number; comments_count: number; corrections_count: number; reports_count: number; pending_count: number; unread_notifications_count: number; }
interface UserPayload { name: string; email: string; status_label: string; is_banned: boolean; banned_until: string | null; ban_reason: string | null; }
interface CategoryOption { id: number; name: string; group: string | null; }
interface PhotoSummary { is_available: boolean; title: string; url: string | null; image_url: string | null; category_summary: string | null; }
interface FavoriteItem { id: number; created_at: string | null; photo: PhotoSummary; }
interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null; }; links: { prev: string | null; next: string | null; }; }
interface MePayload {
    site: SitePayload;
    navigation: NavigationItem[];
    user: UserPayload;
    summary: SummaryPayload;
    filters: { q: string; category_id: number | null; date_from: string; date_to: string; };
    filter_options: { categories: CategoryOption[]; };
    favorites: Paginated<FavoriteItem>;
}

const props = defineProps<{ me: MePayload }>();
const favorites = ref([...props.me.favorites.data]);
const removing = ref<Record<number, boolean>>({});
const status = ref<string | null>(null);
const form = reactive({
    q: props.me.filters.q,
    category_id: props.me.filters.category_id ? String(props.me.filters.category_id) : '',
    date_from: props.me.filters.date_from,
    date_to: props.me.filters.date_to,
});

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const applyFilters = () => {
    router.get('/me/favorites', {
        q: form.q.trim() || undefined,
        category_id: form.category_id || undefined,
        date_from: form.date_from || undefined,
        date_to: form.date_to || undefined,
    }, { preserveScroll: false, preserveState: false, replace: true });
};
const resetFilters = () => router.get('/me/favorites', {}, { preserveScroll: false, preserveState: false, replace: true });
const removeFavorite = async (favorite: FavoriteItem) => {
    if (removing.value[favorite.id]) return;
    removing.value = { ...removing.value, [favorite.id]: true };
    try {
        const response = await fetch('/me/favorites/' + favorite.id, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('remove_failed');
        favorites.value = favorites.value.filter((item) => item.id !== favorite.id);
        status.value = '已取消收藏。';
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
    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader :navigation="me.navigation" :site="me.site" />
        <section class="border-b border-[#90b4ce]/30 bg-[#d8eefe] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <p class="mb-3 text-sm font-semibold text-[#3da9fc]">Favorites</p>
                <h1 class="text-4xl font-semibold sm:text-5xl">我的收藏</h1>
                <p class="mt-4 text-sm text-[#5f6c7b]">共 {{ me.favorites.meta.total }} 条收藏记录</p>
            </div>
        </section>
        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <UserCenterNav :user="me.user" :summary="me.summary" active="favorites" />
                <div class="space-y-6">
                    <form class="grid gap-3 rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-4 shadow-sm lg:grid-cols-5" @submit.prevent="applyFilters">
                        <input v-model="form.q" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm" type="search" placeholder="搜索收藏图片" />
                        <select v-model="form.category_id" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm">
                            <option value="">全部分类</option>
                            <option v-for="category in me.filter_options.categories" :key="category.id" :value="category.id">
                                {{ category.group ? category.group + ' / ' + category.name : category.name }}
                            </option>
                        </select>
                        <input v-model="form.date_from" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm" type="date" />
                        <input v-model="form.date_to" class="rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm" type="date" />
                        <div class="flex gap-2">
                            <button class="rounded-sm bg-[#3da9fc] px-4 py-2 text-sm font-semibold text-[#fffffe]" type="submit">筛选</button>
                            <button class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold" type="button" @click="resetFilters">清空</button>
                        </div>
                    </form>

                    <p v-if="status" class="rounded-sm bg-[#d8eefe] px-4 py-3 text-sm font-semibold">{{ status }}</p>

                    <div v-if="favorites.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <article v-for="favorite in favorites" :key="favorite.id" class="overflow-hidden rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] shadow-sm">
                            <Link :href="favorite.photo.url ?? '/me/favorites'" class="block">
                                <img v-if="favorite.photo.image_url" :src="favorite.photo.image_url" :alt="favorite.photo.title" class="aspect-[4/3] w-full object-cover" />
                                <div v-else class="flex aspect-[4/3] items-center justify-center bg-[#d8eefe] text-[#5f6c7b]"><ImageOff class="h-7 w-7" /></div>
                            </Link>
                            <div class="p-4">
                                <h2 class="line-clamp-1 font-semibold">{{ favorite.photo.title }}</h2>
                                <p class="mt-1 text-xs text-[#5f6c7b]">{{ favorite.photo.category_summary ?? '分类暂不可见' }}</p>
                                <p class="mt-2 text-xs text-[#5f6c7b]">收藏于 {{ favorite.created_at }}</p>
                                <button type="button" class="mt-4 inline-flex items-center gap-2 rounded-sm border border-[#90b4ce]/60 px-3 py-2 text-sm font-semibold hover:border-[#ef4565] hover:text-[#ef4565]" :disabled="removing[favorite.id]" @click="removeFavorite(favorite)">
                                    <Trash2 class="h-4 w-4" />
                                    {{ removing[favorite.id] ? '处理中' : '取消收藏' }}
                                </button>
                            </div>
                        </article>
                    </div>
                    <p v-else class="rounded-sm border border-[#90b4ce]/35 bg-[#fffffe] p-6 text-sm text-[#5f6c7b]">没有符合条件的收藏记录。</p>

                    <div class="flex justify-between gap-3">
                        <Link v-if="me.favorites.links.prev" :href="me.favorites.links.prev" class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold">上一页</Link>
                        <span v-else></span>
                        <Link v-if="me.favorites.links.next" :href="me.favorites.links.next" class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold">下一页</Link>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
