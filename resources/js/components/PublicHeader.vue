<script setup lang="ts">
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
        variant: 'solid',
    },
);

const page = usePage();
const searchQuery = ref('');
const searchSuggestions = ref<SearchSuggestionItem[]>([]);
const suggestionsOpen = ref(false);
const suggestionsLoading = ref(false);
const mobileMenuOpen = ref(false);
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
const canRegister = computed(() => {
    const sharedCanRegister = page.props.canRegister as boolean | undefined;

    return props.canRegister ?? sharedCanRegister ?? true;
});
const currentUser = computed(() => {
    const auth = page.props.auth as
        | { user?: { name?: string } | null }
        | undefined;

    return auth?.user ?? null;
});
const accountLinks = computed(() => {
    if (currentUser.value) {
        return [{ label: '个人中心', url: '/me' }];
    }

    return canRegister.value
        ? [
              { label: '登录', url: '/login' },
              { label: '注册', url: '/register' },
          ]
        : [{ label: '登录', url: '/login' }];
});
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
        const response = await fetch(`/search/suggestions?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

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
            class="mx-auto hidden h-20 max-w-7xl grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-6 px-4 sm:px-6 lg:grid lg:px-8"
        >
            <nav
                class="flex min-w-0 items-center gap-5 text-sm font-medium"
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
                class="flex min-w-0 shrink-0 items-center justify-center gap-3"
                aria-label="返回首页"
            >
                <img
                    v-if="site.logo_url"
                    :src="site.logo_url"
                    :alt="site.name"
                    class="h-10 w-10 rounded-sm object-cover"
                />
                <span
                    v-else
                    class="flex h-10 w-10 items-center justify-center rounded-sm bg-[#3da9fc] text-sm font-bold text-[#fffffe]"
                >
                    M
                </span>
                <span class="max-w-48 truncate text-lg font-semibold">{{
                    site.name
                }}</span>
            </Link>

            <div class="flex min-w-0 items-center justify-end gap-3">
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
                        class="w-full bg-transparent text-sm outline-none placeholder:text-current/70"
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
                            v-if="suggestionsLoading && searchSuggestions.length === 0"
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
                            <span class="block text-sm font-semibold">{{ item.label }}</span>
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
                    class="flex shrink-0 items-center gap-2 text-sm font-medium"
                    aria-label="账号入口"
                >
                    <Link
                        v-for="link in accountLinks"
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
                class="flex min-w-0 shrink-0 items-center gap-3"
                aria-label="返回首页"
            >
                <img
                    v-if="site.logo_url"
                    :src="site.logo_url"
                    :alt="site.name"
                    class="h-10 w-10 rounded-sm object-cover"
                />
                <span
                    v-else
                    class="flex h-10 w-10 items-center justify-center rounded-sm bg-[#3da9fc] text-sm font-bold text-[#fffffe]"
                >
                    M
                </span>
                <span class="truncate text-base font-semibold sm:text-lg">{{
                    site.name
                }}</span>
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
                        v-if="suggestionsLoading && searchSuggestions.length === 0"
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
                        <span class="block text-sm font-semibold">{{ item.label }}</span>
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
                class="mt-4 flex gap-2 border-t border-[#90b4ce]/35 pt-4"
                aria-label="移动端账号入口"
            >
                <Link
                    v-for="link in accountLinks"
                    :key="link.label"
                    :href="link.url"
                    class="rounded-sm border border-[#90b4ce]/60 px-4 py-2 text-sm font-semibold hover:border-[#3da9fc] hover:text-[#3da9fc]"
                    @click="mobileMenuOpen = false"
                >
                    {{ link.label }}
                </Link>
            </nav>
        </div>
    </header>
</template>