<script setup lang="ts">
import { logout } from '@/routes';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu, Search, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface SitePayload {
    name: string;
    logo_url: string | null;
    search_placeholder: string;
}

interface SearchSuggestionItem {
    term: string;
    label: string;
    description: string | null;
    url: string;
    source: 'recommendation' | 'hot';
}

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        navigation: NavigationItem[];
        site: SitePayload;
        variant?: 'overlay' | 'solid';
    }>(),
    {
        canRegister: true,
        variant: 'solid',
    },
);

const page = usePage();
const searchQuery = ref('');
const searchSuggestions = ref<SearchSuggestionItem[]>([]);
const suggestionsOpen = ref(false);
const suggestionsLoading = ref(false);
const mobileMenuOpen = ref(false);
const accountMenuOpen = ref(false);
const hasScrolled = ref(false);

let suggestionsTimer: number | undefined;
let closeSuggestionsTimer: number | undefined;
let suggestionsAbortController: AbortController | null = null;

const publicNavigationUrls = [
    '/',
    '/photos',
    '/albums',
    '/topics',
    '/timeline',
    '/rankings',

    '/support',
];
const isSolidHeader = computed(
    () =>
        props.variant === 'solid' || hasScrolled.value || mobileMenuOpen.value,
);
const visibleNavigationItems = computed(() =>
    props.navigation.filter((item) => publicNavigationUrls.includes(item.url)),
);
const canRegister = computed(() => props.canRegister ?? true);
const currentUser = computed(() => {
    const auth = page.props.auth as
        | { user?: { name?: string } | null }
        | undefined;

    return auth?.user ?? null;
});
const canAccessAdmin = computed(() => Boolean(page.props.canAccessAdmin));
const guestAccountLinks = computed(() =>
    canRegister.value
        ? [
              { label: '登录', url: '/login' },
              { label: '注册', url: '/register' },
          ]
        : [{ label: '登录', url: '/login' }],
);
const showSuggestionPanel = computed(
    () =>
        suggestionsOpen.value &&
        (suggestionsLoading.value || searchSuggestions.value.length > 0),
);

const searchUrl = (term: string) =>
    `/search?q=${encodeURIComponent(term.trim())}`;

const fetchSearchSuggestions = async () => {
    suggestionsAbortController?.abort();

    const controller = new AbortController();
    const query = searchQuery.value.trim();
    const params = new URLSearchParams();

    if (query) {
        params.set('q', query);
    }

    suggestionsAbortController = controller;
    suggestionsLoading.value = true;

    try {
        const response = await fetch(
            `/search/suggestions?${params.toString()}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            searchSuggestions.value = [];
            return;
        }

        const payload = (await response.json()) as {
            items?: SearchSuggestionItem[];
        };

        searchSuggestions.value = Array.isArray(payload.items)
            ? payload.items
            : [];
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            searchSuggestions.value = [];
        }
    } finally {
        if (suggestionsAbortController === controller) {
            suggestionsLoading.value = false;
            suggestionsAbortController = null;
        }
    }
};

const scheduleSearchSuggestions = () => {
    if (suggestionsTimer) {
        window.clearTimeout(suggestionsTimer);
    }

    suggestionsTimer = window.setTimeout(() => {
        void fetchSearchSuggestions();
    }, 180);
};

const openSuggestions = () => {
    if (closeSuggestionsTimer) {
        window.clearTimeout(closeSuggestionsTimer);
    }

    suggestionsOpen.value = true;

    if (searchSuggestions.value.length === 0) {
        scheduleSearchSuggestions();
    }
};

const closeSuggestionsSoon = () => {
    closeSuggestionsTimer = window.setTimeout(() => {
        suggestionsOpen.value = false;
    }, 120);
};

const chooseSuggestion = (item: SearchSuggestionItem) => {
    searchQuery.value = item.term;
    suggestionsOpen.value = false;
    mobileMenuOpen.value = false;
    router.visit(item.url || searchUrl(item.term));
};

watch(searchQuery, () => {
    if (suggestionsOpen.value) {
        scheduleSearchSuggestions();
    }
});

const submitSearch = () => {
    const query = searchQuery.value.trim();

    suggestionsOpen.value = false;
    router.visit(query ? searchUrl(query) : '/search');
    mobileMenuOpen.value = false;
};

const handleLogout = () => {
    accountMenuOpen.value = false;
    mobileMenuOpen.value = false;
    router.post(logout());
};

const onScroll = () => {
    hasScrolled.value = window.scrollY > 24;
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);

    if (suggestionsTimer) {
        window.clearTimeout(suggestionsTimer);
    }

    if (closeSuggestionsTimer) {
        window.clearTimeout(closeSuggestionsTimer);
    }

    suggestionsAbortController?.abort();
});
</script>

<template>
    <header
        class="inset-x-0 top-0 z-50 transition-all duration-300"
        :class="[
            variant === 'overlay' ? 'fixed' : 'sticky',
            isSolidHeader
                ? 'bg-[#fffffe]/95 text-[#094067] shadow-sm backdrop-blur'
                : 'bg-transparent text-[#fffffe]',
        ]"
    >
        <div
            class="mx-auto hidden h-20 max-w-7xl grid-cols-[auto_minmax(0,1fr)_minmax(320px,1.2fr)] items-center gap-5 px-4 sm:px-6 lg:grid lg:px-8"
        >
            <nav
                class="order-2 flex min-w-0 items-center gap-5 text-base font-medium"
                aria-label="主导航"
            >
                <Link
                    v-for="item in visibleNavigationItems"
                    :key="item.label"
                    :href="item.url"
                    class="whitespace-nowrap transition hover:text-[#3da9fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <Link
                href="/"
                class="group relative order-1 flex min-w-0 shrink-0 items-center justify-start gap-3"
                aria-label="返回首页"
                :title="site.name"
            >
                <img
                    v-if="site.logo_url"
                    :src="site.logo_url"
                    :alt="site.name"
                    class="h-10 w-auto max-w-[10rem] rounded-sm object-contain"
                />
                <span
                    v-else
                    class="flex h-10 w-10 items-center justify-center rounded-sm bg-[#3da9fc] text-sm font-bold text-[#fffffe]"
                >
                    M
                </span>
                <span
                    class="pointer-events-none absolute top-full left-0 z-20 mt-2 whitespace-nowrap rounded-sm bg-[#094067] px-3 py-2 text-sm font-medium text-[#fffffe] opacity-0 shadow-sm transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
                >
                    {{ site.name }}
                </span>
            </Link>

            <div class="order-3 flex min-w-0 items-center justify-end gap-3">
                <form
                    class="relative flex max-w-md min-w-[220px] flex-1 items-center rounded-full border px-4 py-2 transition"
                    :class="
                        isSolidHeader
                            ? 'border-[#90b4ce]/60 bg-[#fffffe] text-[#094067]'
                            : 'border-[#fffffe]/35 bg-[#094067]/20 text-[#fffffe] backdrop-blur'
                    "
                    role="search"
                    @submit.prevent="submitSearch"
                >
                    <Search class="mr-2 h-4 w-4 shrink-0" aria-hidden="true" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        :placeholder="site.search_placeholder"
                        class="w-full bg-transparent text-base outline-none placeholder:text-current/70"
                        aria-label="搜索图片、相册、赛事或年份"
                        autocomplete="off"
                        @focus="openSuggestions"
                        @blur="closeSuggestionsSoon"
                    />
                    <div
                        v-if="showSuggestionPanel"
                        class="absolute top-full right-0 left-0 mt-2 overflow-hidden rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] text-[#094067] shadow-lg"
                    >
                        <p
                            v-if="
                                suggestionsLoading &&
                                searchSuggestions.length === 0
                            "
                            class="px-4 py-3 text-sm text-[#5f6c7b]"
                        >
                            正在加载建议
                        </p>
                        <button
                            v-for="item in searchSuggestions"
                            :key="`${item.source}-${item.term}`"
                            type="button"
                            class="block w-full px-4 py-3 text-left transition hover:bg-[#d8eefe] focus-visible:bg-[#d8eefe] focus-visible:outline-none"
                            @mousedown.prevent="chooseSuggestion(item)"
                        >
                            <span class="block text-sm font-semibold">{{
                                item.label
                            }}</span>
                            <span
                                v-if="item.description"
                                class="mt-0.5 block text-xs text-[#5f6c7b]"
                            >
                                {{ item.description }}
                            </span>
                        </button>
                    </div>
                </form>

                <nav
                    class="flex shrink-0 items-center gap-2 text-base font-medium"
                    aria-label="账号入口"
                >
                    <template v-if="currentUser">
                        <div class="relative">
                            <button
                                type="button"
                                class="rounded-sm px-3 py-2 whitespace-nowrap transition hover:bg-[#d8eefe] hover:text-[#094067] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                                :aria-expanded="accountMenuOpen"
                                aria-haspopup="menu"
                                @click="accountMenuOpen = !accountMenuOpen"
                            >
                                个人中心
                            </button>
                            <div
                                v-if="accountMenuOpen"
                                class="absolute top-full right-0 z-10 mt-2 min-w-40 rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] p-1 text-[#094067] shadow-lg"
                                role="menu"
                            >
                                <Link
                                    href="/me"
                                    class="block rounded-sm px-3 py-2 hover:bg-[#d8eefe]"
                                    role="menuitem"
                                    @click="accountMenuOpen = false"
                                    >个人中心</Link
                                >
                                <a
                                    v-if="canAccessAdmin"
                                    href="/admin"
                                    class="block rounded-sm px-3 py-2 hover:bg-[#d8eefe]"
                                    role="menuitem"
                                    @click="accountMenuOpen = false"
                                    >管理后台</a
                                >
                                <button
                                    type="button"
                                    class="block w-full rounded-sm px-3 py-2 text-left hover:bg-[#d8eefe]"
                                    role="menuitem"
                                    @click="handleLogout"
                                >
                                    退出登录
                                </button>
                            </div>
                        </div>
                    </template>
                    <Link
                        v-else
                        v-for="link in guestAccountLinks"
                        :key="link.label"
                        :href="link.url"
                        class="rounded-sm px-3 py-2 whitespace-nowrap transition hover:bg-[#d8eefe] hover:text-[#094067] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                    >
                        {{ link.label }}
                    </Link>
                </nav>
            </div>
        </div>

        <div
            class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:hidden"
        >
            <Link
                href="/"
                class="group relative flex min-w-0 shrink-0 items-center gap-3"
                aria-label="返回首页"
                :title="site.name"
            >
                <img
                    v-if="site.logo_url"
                    :src="site.logo_url"
                    :alt="site.name"
                    class="h-10 w-auto max-w-[10rem] rounded-sm object-contain"
                />
                <span
                    v-else
                    class="flex h-10 w-10 items-center justify-center rounded-sm bg-[#3da9fc] text-sm font-bold text-[#fffffe]"
                >
                    M
                </span>
                <span
                    class="pointer-events-none absolute top-full left-0 z-20 mt-2 whitespace-nowrap rounded-sm bg-[#094067] px-3 py-2 text-sm font-medium text-[#fffffe] opacity-0 shadow-sm transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
                >
                    {{ site.name }}
                </span>
            </Link>

            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-current/25"
                :aria-label="mobileMenuOpen ? '关闭导航' : '打开导航'"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <X v-if="mobileMenuOpen" class="h-5 w-5" aria-hidden="true" />
                <Menu v-else class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>

        <div
            v-if="mobileMenuOpen"
            class="border-t border-[#90b4ce]/35 bg-[#fffffe] px-4 py-5 text-[#094067] lg:hidden"
        >
            <form
                class="relative mb-5 flex items-center rounded-full border border-[#90b4ce]/60 px-4 py-2"
                role="search"
                @submit.prevent="submitSearch"
            >
                <Search class="mr-2 h-4 w-4 shrink-0" aria-hidden="true" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="site.search_placeholder"
                    class="w-full bg-transparent text-sm outline-none placeholder:text-[#5f6c7b]"
                    aria-label="搜索图片、相册、赛事或年份"
                    autocomplete="off"
                    @focus="openSuggestions"
                    @blur="closeSuggestionsSoon"
                />
                <div
                    v-if="showSuggestionPanel"
                    class="absolute top-full right-0 left-0 z-10 mt-2 overflow-hidden rounded-sm border border-[#90b4ce]/30 bg-[#fffffe] text-[#094067] shadow-lg"
                >
                    <p
                        v-if="
                            suggestionsLoading && searchSuggestions.length === 0
                        "
                        class="px-4 py-3 text-sm text-[#5f6c7b]"
                    >
                        正在加载建议
                    </p>
                    <button
                        v-for="item in searchSuggestions"
                        :key="`${item.source}-mobile-${item.term}`"
                        type="button"
                        class="block w-full px-4 py-3 text-left transition hover:bg-[#d8eefe] focus-visible:bg-[#d8eefe] focus-visible:outline-none"
                        @mousedown.prevent="chooseSuggestion(item)"
                    >
                        <span class="block text-sm font-semibold">{{
                            item.label
                        }}</span>
                        <span
                            v-if="item.description"
                            class="mt-0.5 block text-xs text-[#5f6c7b]"
                        >
                            {{ item.description }}
                        </span>
                    </button>
                </div>
            </form>

            <nav class="grid gap-2" aria-label="移动端主导航">
                <Link
                    v-for="item in visibleNavigationItems"
                    :key="item.label"
                    :href="item.url"
                    class="rounded-sm px-2 py-2 text-base font-medium hover:bg-[#d8eefe]"
                    @click="mobileMenuOpen = false"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <nav
                class="mt-4 flex flex-wrap gap-2 border-t border-[#90b4ce]/35 pt-4"
                aria-label="移动端账号入口"
            >
                <template v-if="currentUser">
                    <Link
                        href="/me"
                        class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-base font-semibold hover:border-[#3da9fc] hover:text-[#3da9fc]"
                        @click="mobileMenuOpen = false"
                        >个人中心</Link
                    >
                    <a
                        v-if="canAccessAdmin"
                        href="/admin"
                        class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-base font-semibold hover:border-[#3da9fc] hover:text-[#3da9fc]"
                        @click="mobileMenuOpen = false"
                        >管理后台</a
                    >
                    <button
                        type="button"
                        class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-base font-semibold hover:border-[#3da9fc] hover:text-[#3da9fc]"
                        @click="handleLogout"
                    >
                        退出登录
                    </button>
                </template>
                <Link
                    v-else
                    v-for="link in guestAccountLinks"
                    :key="link.label"
                    :href="link.url"
                    class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-base font-semibold hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    @click="mobileMenuOpen = false"
                >
                    {{ link.label }}
                </Link>
            </nav>
        </div>
    </header>
</template>
