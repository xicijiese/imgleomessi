<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface NavigationItem {
    label: string;
    url: string;
}

interface HeroSlide {
    title: string;
    subtitle: string | null;
    button_label: string | null;
    button_url: string | null;
    desktop_image_url: string | null;
    mobile_image_url: string | null;
}

interface ShowcaseItem {
    id: string;
    type: 'photo' | 'album';
    title: string;
    url: string;
    image_url: string | null;
    alt: string;
}

interface CategoryTab {
    label: string;
    category_id: number | null;
    items: ShowcaseItem[];
}

interface TopicItem {
    title: string;
    url: string;
    cover_image_url: string | null;
}

interface HomePayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    hero_slides: HeroSlide[];
    category_module: {
        enabled: boolean;
        title: string;
        more_url: string;
        tabs: CategoryTab[];
    };
    latest_photos: {
        enabled: boolean;
        title: string;
        display_count: number;
        items: ShowcaseItem[];
    };
    topic_module: {
        enabled: boolean;
        title: string;
        items: TopicItem[];
    };
    footer: {
        copyright_text: string;
        icp_text: string | null;
        links: NavigationItem[];
        social_links: NavigationItem[];
    };
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        home: HomePayload;
    }>(),
    {
        canRegister: true,
    },
);

const activeHeroIndex = ref(0);
const activeCategoryIndex = ref(0);
let heroTimer: number | null = null;

const activeHero = computed(
    () =>
        props.home.hero_slides[activeHeroIndex.value] ??
        props.home.hero_slides[0],
);
const activeCategoryTab = computed(
    () => props.home.category_module.tabs[activeCategoryIndex.value],
);
const showCategoryModule = computed(
    () =>
        props.home.category_module.enabled &&
        props.home.category_module.tabs.length > 0,
);
const showLatestPhotos = computed(() => props.home.latest_photos.enabled);
const hasLatestPhotos = computed(
    () => props.home.latest_photos.items.length > 0,
);
const showTopics = computed(() => props.home.topic_module.enabled);
const hasTopics = computed(() => props.home.topic_module.items.length > 0);

const setCategoryTab = (index: number) => {
    activeCategoryIndex.value = index;
};

const setHero = (index: number) => {
    activeHeroIndex.value = index;
};

onMounted(() => {
    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    if (!reduceMotion && props.home.hero_slides.length > 1) {
        heroTimer = window.setInterval(() => {
            activeHeroIndex.value =
                (activeHeroIndex.value + 1) % props.home.hero_slides.length;
        }, 6500);
    }
});

onUnmounted(() => {
    if (heroTimer !== null) {
        window.clearInterval(heroTimer);
    }
});
</script>

<template>
    <SeoHead :seo="home.seo" />

    <main class="min-h-screen bg-[#fffffe] text-[#094067]">
        <PublicHeader
            :can-register="canRegister"
            :navigation="home.navigation"
            :site="home.site"
            variant="overlay"
        />

        <section
            class="relative min-h-[86vh] overflow-hidden bg-[#094067] text-[#fffffe] sm:min-h-[92vh]"
        >
            <template v-if="activeHero">
                <picture
                    v-if="
                        activeHero.desktop_image_url ||
                        activeHero.mobile_image_url
                    "
                >
                    <source
                        v-if="activeHero.mobile_image_url"
                        :srcset="activeHero.mobile_image_url"
                        media="(max-width: 767px)"
                    />
                    <img
                        :src="
                            activeHero.desktop_image_url ||
                            activeHero.mobile_image_url ||
                            ''
                        "
                        :alt="activeHero.title"
                        class="absolute inset-0 h-full w-full object-cover motion-safe:animate-[pulse_12s_ease-in-out_infinite]"
                    />
                </picture>
                <div
                    v-else
                    class="absolute inset-0 bg-[radial-gradient(circle_at_24%_18%,#3da9fc_0,transparent_28%),linear-gradient(135deg,#094067_0%,#0f5f8f_48%,#d8eefe_130%)]"
                />
                <div class="absolute inset-0 bg-[#094067]/58" />

                <div
                    class="relative z-10 mx-auto flex min-h-[86vh] max-w-7xl items-center px-4 pt-32 pb-20 sm:min-h-[92vh] sm:px-6 lg:px-8"
                >
                    <div class="max-w-3xl">
                        <p
                            class="mb-5 text-sm font-semibold text-[#90b4ce] uppercase"
                        >
                            Messi Image Archive
                        </p>
                        <h1
                            class="text-4xl leading-tight font-semibold sm:text-6xl lg:text-7xl"
                        >
                            {{ activeHero.title }}
                        </h1>
                        <p
                            v-if="activeHero.subtitle"
                            class="mt-6 max-w-2xl text-base leading-8 text-[#d8eefe] sm:text-lg"
                        >
                            {{ activeHero.subtitle }}
                        </p>
                        <div class="mt-9 flex flex-wrap gap-4">
                            <Link
                                v-if="
                                    activeHero.button_label &&
                                    activeHero.button_url
                                "
                                :href="activeHero.button_url"
                                class="rounded-sm bg-[#3da9fc] px-6 py-3 text-sm font-semibold text-[#fffffe] transition hover:bg-[#1e94e6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#fffffe]"
                            >
                                {{ activeHero.button_label }}
                            </Link>
                            <Link
                                href="/albums"
                                class="rounded-sm border border-[#fffffe]/55 px-6 py-3 text-sm font-semibold text-[#fffffe] transition hover:border-[#3da9fc] hover:text-[#3da9fc]"
                            >
                                浏览相册
                            </Link>
                        </div>
                    </div>
                </div>

                <div
                    v-if="home.hero_slides.length > 1"
                    class="absolute bottom-8 left-1/2 z-20 flex -translate-x-1/2 gap-3"
                >
                    <button
                        v-for="(_slide, index) in home.hero_slides"
                        :key="index"
                        type="button"
                        class="h-2.5 rounded-full transition-all"
                        :class="
                            activeHeroIndex === index
                                ? 'w-8 bg-[#fffffe]'
                                : 'w-2.5 bg-[#fffffe]/45'
                        "
                        :aria-label="`切换到第 ${index + 1} 张头图`"
                        @click="setHero(index)"
                    />
                </div>
            </template>
        </section>

        <section
            v-if="showCategoryModule"
            class="bg-[#fffffe] px-4 py-16 sm:px-6 sm:py-20 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <div class="mb-5">
                    <h2 class="text-3xl font-semibold sm:text-4xl">
                        {{ home.category_module.title }}
                    </h2>
                </div>

                <div class="mb-8 flex items-center justify-between gap-4">
                    <div
                        class="min-w-0 flex-1 overflow-x-auto pb-2"
                        role="tablist"
                        aria-label="精选相册子分类导航"
                    >
                        <div class="flex w-max gap-3">
                            <button
                                v-for="(tab, index) in home.category_module.tabs"
                                :key="`${tab.label}-${tab.category_id ?? index}`"
                                type="button"
                                class="shrink-0 rounded-full border px-5 py-2 text-sm font-semibold transition"
                                :class="
                                    activeCategoryIndex === index
                                        ? 'border-[#094067] bg-[#094067] text-[#fffffe]'
                                        : 'border-[#90b4ce]/60 text-[#094067] hover:border-[#3da9fc] hover:text-[#3da9fc]'
                                "
                                role="tab"
                                :aria-selected="activeCategoryIndex === index"
                                @click="setCategoryTab(index)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>
                    <Link
                        :href="home.category_module.more_url"
                        class="shrink-0 pb-2 text-sm font-semibold text-[#3da9fc] hover:text-[#094067]"
                    >
                        更多
                    </Link>
                </div>

                <div
                    v-if="activeCategoryTab?.items.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="item in activeCategoryTab.items"
                        :key="item.id"
                        :href="item.url"
                        class="group relative aspect-[4/3] overflow-hidden rounded-sm bg-[#d8eefe] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                        :aria-label="`查看相册：${item.title}`"
                    >
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.alt"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:transition-none"
                        />
                        <div
                            v-else
                            class="h-full w-full bg-[linear-gradient(135deg,#d8eefe,#90b4ce)]"
                        />
                        <div
                            class="absolute inset-0 bg-[#094067]/42 transition-colors duration-200 md:bg-[#094067]/0 md:group-hover:bg-[#094067]/58 motion-reduce:transition-none"
                        />
                        <div
                            class="absolute inset-x-0 bottom-0 p-5 opacity-100 transition-opacity duration-200 md:opacity-0 md:group-hover:opacity-100 motion-reduce:transition-none"
                        >
                            <span class="text-lg font-semibold text-[#fffffe] drop-shadow-sm">
                                {{ item.title }}
                            </span>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="rounded-sm bg-[#d8eefe] px-6 py-10 text-[#5f6c7b]"
                >
                    当前分类还没有可公开展示的图片或相册。
                </div>
            </div>
        </section>

        <section
            v-if="showLatestPhotos"
            class="bg-[#d8eefe] px-4 py-16 sm:px-6 sm:py-20 lg:px-8"
        >
            <div class="mx-auto max-w-[1200px]">
                <div class="mb-8 flex items-end justify-between gap-5">
                    <h2 class="text-3xl font-semibold sm:text-4xl">
                        {{ home.latest_photos.title }}
                    </h2>
                    <Link
                        href="/photos"
                        class="hidden text-sm font-semibold text-[#3da9fc] hover:text-[#094067] sm:inline"
                    >
                        全部照片
                    </Link>
                </div>

                <div
                    v-if="hasLatestPhotos"
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="item in home.latest_photos.items"
                        :key="item.id"
                        :href="item.url"
                        class="group block aspect-[4/3] overflow-hidden rounded-sm bg-[#fffffe] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#3da9fc]"
                        :aria-label="`查看图片：${item.title}`"
                    >
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.alt"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-105 group-hover:brightness-95"
                        />
                        <div
                            v-else
                            class="h-full w-full bg-[linear-gradient(135deg,#fffffe,#90b4ce)]"
                        />
                    </Link>
                </div>

                <div
                    v-else
                    class="rounded-sm border border-[#90b4ce]/45 bg-[#fffffe] px-6 py-10 text-[#5f6c7b]"
                >
                    最新照片模块已启用，当前还没有可公开展示的图片。
                </div>
            </div>
        </section>
        <section
            v-if="showTopics"
            class="bg-[#094067] py-16 text-[#fffffe] sm:py-20"
        >
            <div class="mb-8 px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <h2 class="text-3xl font-semibold sm:text-4xl">
                        {{ home.topic_module.title }}
                    </h2>
                </div>
            </div>

            <div
                v-if="hasTopics"
                class="flex gap-3 overflow-x-auto px-4 pb-2 sm:px-6 lg:px-8"
            >
                <Link
                    v-for="item in home.topic_module.items"
                    :key="item.title"
                    :href="item.url"
                    class="group relative h-72 w-[82vw] shrink-0 overflow-hidden rounded-sm bg-[#0f5f8f] sm:w-[360px] lg:w-[420px]"
                >
                    <img
                        v-if="item.cover_image_url"
                        :src="item.cover_image_url"
                        :alt="item.title"
                        loading="lazy"
                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                    <div
                        v-else
                        class="h-full w-full bg-[linear-gradient(135deg,#0f5f8f,#3da9fc)]"
                    />
                    <div
                        class="absolute inset-0 bg-[#094067]/48 transition group-hover:bg-[#094067]/62"
                    />
                    <div
                        class="absolute inset-0 flex items-center justify-center p-6 text-center"
                    >
                        <span class="text-2xl font-semibold">{{
                            item.title
                        }}</span>
                    </div>
                </Link>
            </div>

            <div v-else class="px-4 pb-2 sm:px-6 lg:px-8">
                <div
                    class="mx-auto max-w-7xl rounded-sm border border-[#90b4ce]/35 bg-[#fffffe]/8 px-6 py-10 text-[#d8eefe]"
                >
                    专题模块已启用，当前还没有可公开展示的专题配置。
                </div>
            </div>
        </section>
    </main>
</template>
