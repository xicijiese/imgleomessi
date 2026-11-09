<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import SharePanel from '@/components/SharePanel.vue';
import { useGalleryScrollReveal } from '@/composables/useGalleryScrollReveal';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import {
    ArrowRight,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    Share2,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

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
    desktop_focus?: { x: number; y: number };
    mobile_focus?: { x: number; y: number };
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

const homepage = ref<HTMLElement | null>(null);
useGalleryScrollReveal(
    homepage,
    () => [props.home, activeCategoryTab.value],
    '.home-section > .section-heading, .album-grid, .photo-grid > .photo-card, .topic-carousel',
);
let motion: gsap.MatchMedia | undefined;
let categoryMotion: gsap.core.Tween | undefined;
let categoryRequest = 0;
const categoryNav = ref<HTMLElement | null>(null);
const categoryMarker = ref({ left: 0, width: 0 });
let categoryObserver: ResizeObserver | undefined;
const albumHoverTweens = new Map<HTMLElement, gsap.core.Tween>();
const clearAlbumHover = () => {
    albumHoverTweens.forEach((tween) => tween.revert());
    albumHoverTweens.clear();
};
const updateCategoryMarker = () => {
    const button = categoryNav.value?.querySelector<HTMLElement>('.is-active');
    if (button)
        categoryMarker.value = {
            left: button.offsetLeft,
            width: button.offsetWidth,
        };
};
const albumHover = (event: PointerEvent, show: boolean) => {
    if (
        event.pointerType === 'touch' ||
        !window.matchMedia(
            '(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)',
        ).matches
    )
        return;
    const card = event.currentTarget as HTMLElement;
    const overlay = card.querySelector<HTMLElement>('.album-caption');
    if (!overlay || card.matches(':focus-visible')) return;
    const r = card.getBoundingClientRect();
    const distances = [
        event.clientY - r.top,
        r.right - event.clientX,
        r.bottom - event.clientY,
        event.clientX - r.left,
    ];
    const edge = distances.indexOf(Math.min(...distances));
    const pose = [
        { rotationX: -90, rotationY: 0, transformOrigin: '50% 0%' },
        { rotationX: 0, rotationY: -90, transformOrigin: '100% 50%' },
        { rotationX: 90, rotationY: 0, transformOrigin: '50% 100%' },
        { rotationX: 0, rotationY: 90, transformOrigin: '0% 50%' },
    ][edge];
    albumHoverTweens.get(overlay)?.kill();
    if (show && Number(gsap.getProperty(overlay, 'opacity')) < 0.01)
        gsap.set(overlay, { ...pose, autoAlpha: 0 });
    if (!show) gsap.set(overlay, { transformOrigin: pose.transformOrigin });
    albumHoverTweens.set(
        overlay,
        gsap.to(overlay, {
            ...(show
                ? { rotationX: 0, rotationY: 0, autoAlpha: 1 }
                : { ...pose, autoAlpha: 0 }),
            duration: show ? 0.3 : 0.22,
            ease: 'power2.out',
            overwrite: true,
        }),
    );
};
const topicTrack = ref<HTMLElement | null>(null);
const activeTopicIndex = ref(0);
let topicObserver: ResizeObserver | undefined;
let topicScrollTimer: ReturnType<typeof setTimeout> | undefined;
let topicProgrammaticScroll = false;
const centerTopic = (index: number, animate = true) => {
    const track = topicTrack.value;
    const card = track?.querySelectorAll<HTMLElement>('.topic-card')[index];
    if (!track || !card || typeof track.scrollTo !== 'function') return;
    activeTopicIndex.value = index;
    const centeredLeft =
        track.scrollLeft +
        card.getBoundingClientRect().left -
        track.getBoundingClientRect().left -
        (track.clientWidth - card.clientWidth) / 2;
    const left = Math.max(
        0,
        Math.min(centeredLeft, track.scrollWidth - track.clientWidth),
    );
    topicProgrammaticScroll = true;
    track.scrollTo({
        left,
        behavior:
            animate &&
            !window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'smooth'
                : 'instant',
    });
    syncTopic();
};
const moveTopic = (direction: number) => {
    const count = props.home.topic_module.items.length;
    if (count > 1)
        centerTopic((activeTopicIndex.value + direction + count) % count);
};
const selectNearestTopic = () => {
    const track = topicTrack.value;
    if (!track) return;
    const center = track.getBoundingClientRect().left + track.clientWidth / 2;
    let nearest = 0;
    let distance = Infinity;
    track
        .querySelectorAll<HTMLElement>('.topic-card')
        .forEach((card, index) => {
            const delta = Math.abs(
                card.getBoundingClientRect().left +
                    card.clientWidth / 2 -
                    center,
            );
            if (delta < distance) {
                nearest = index;
                distance = delta;
            }
        });
    activeTopicIndex.value = nearest;
};
const syncTopic = () => {
    clearTimeout(topicScrollTimer);
    topicScrollTimer = setTimeout(() => {
        // 首尾贴边时保留箭头选中的专题，手动滚动才按视口中心重新选中。
        if (topicProgrammaticScroll) topicProgrammaticScroll = false;
        else {
            selectNearestTopic();
            centerTopic(activeTopicIndex.value);
        }
    }, 150);
};
const beginManualTopicScroll = () => {
    topicProgrammaticScroll = false;
};
const activeHeroIndex = ref(0);
const activeCategoryIndex = ref(0);
let heroTimer: number | null = null;
const showBackToTop = ref(false);
const updateScrollPosition = () => {
    showBackToTop.value = window.scrollY > 400;
};
const scrollToTop = () => {
    window.scrollTo({
        top: 0,
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'instant'
            : 'smooth',
    });
};

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
    if (!props.home.category_module.tabs[index]) return;
    const ticket = ++categoryRequest;
    categoryMotion?.revert();
    if (homepage.value)
        gsap.set(homepage.value.querySelectorAll('.album-card'), {
            clearProps: 'opacity,transform',
        });
    clearAlbumHover();
    if (activeCategoryIndex.value === index) return;
    const reduced = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    const apply = async () => {
        if (ticket !== categoryRequest) return;
        activeCategoryIndex.value = index;
        await nextTick();
        if (ticket !== categoryRequest || !homepage.value) return;
        updateCategoryMarker();
        const nav = categoryNav.value;
        const button = nav?.querySelector<HTMLElement>('.is-active');
        if (nav && button) {
            const left = button.offsetLeft,
                right = left + button.offsetWidth;
            if (left < nav.scrollLeft)
                nav.scrollTo({
                    left,
                    behavior: reduced ? 'instant' : 'smooth',
                });
            else if (right > nav.scrollLeft + nav.clientWidth)
                nav.scrollTo({
                    left: right - nav.clientWidth,
                    behavior: reduced ? 'instant' : 'smooth',
                });
        }
        if (!reduced)
            categoryMotion = gsap.fromTo(
                homepage.value.querySelectorAll('.album-card'),
                { opacity: 0, y: 8 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.26,
                    stagger: 0.025,
                    ease: 'power2.out',
                    clearProps: 'opacity,transform',
                },
            );
    };
    const cards = homepage.value?.querySelectorAll('.album-card');
    if (reduced || !cards?.length) void apply();
    else
        categoryMotion = gsap.to(cards, {
            opacity: 0,
            y: -5,
            duration: 0.12,
            onComplete: () => void apply(),
        });
};
const categoryKey = (event: KeyboardEvent, index: number) => {
    const length = props.home.category_module.tabs.length;
    const next =
        event.key === 'ArrowRight'
            ? (index + 1) % length
            : event.key === 'ArrowLeft'
              ? (index + length - 1) % length
              : event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? length - 1
                  : -1;
    if (next < 0) return;
    event.preventDefault();
    categoryNav.value
        ?.querySelectorAll<HTMLButtonElement>('.category-tab')
        [next]?.focus({ preventScroll: true });
    setCategoryTab(next);
};
const setHero = (index: number) => {
    activeHeroIndex.value = index;
};
watch(
    () => props.home.category_module.tabs,
    async () => {
        await nextTick();
        updateCategoryMarker();
    },
);

onMounted(() => {
    updateCategoryMarker();
    categoryObserver = new ResizeObserver(updateCategoryMarker);
    if (categoryNav.value) categoryObserver.observe(categoryNav.value);
    void document.fonts.ready.then(() => {
        if (homepage.value) updateCategoryMarker();
    });
    motion = gsap.matchMedia();
    motion.add(
        '(prefers-reduced-motion: no-preference)',
        () => {
            gsap.timeline({
                defaults: { duration: 0.45, ease: 'power2.out' },
            }).from('.hero-copy > *', {
                y: 12,
                stagger: 0.06,
                clearProps: 'transform',
            });
        },
        homepage.value ?? undefined,
    );
    if (topicTrack.value && typeof ResizeObserver !== 'undefined') {
        let previousWidth = 0;
        topicObserver = new ResizeObserver(([entry]) => {
            const width = entry.contentRect.width;
            if (width === previousWidth) return;
            if (previousWidth === 0) {
                topicTrack.value?.scrollTo({ left: 0, behavior: 'instant' });
                selectNearestTopic();
            } else centerTopic(activeTopicIndex.value, false);
            previousWidth = width;
        });
        topicObserver.observe(topicTrack.value);
    }
    updateScrollPosition();
    window.addEventListener('scroll', updateScrollPosition, { passive: true });
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
    categoryRequest++;
    categoryObserver?.disconnect();
    clearAlbumHover();
    topicObserver?.disconnect();
    clearTimeout(topicScrollTimer);
    motion?.revert();
    categoryMotion?.revert();
    window.removeEventListener('scroll', updateScrollPosition);
    if (heroTimer !== null) {
        window.clearInterval(heroTimer);
    }
});
</script>

<template>
    <SeoHead :seo="home.seo" />
    <main ref="homepage" class="archive-home">
        <PublicHeader
            :can-register="canRegister"
            :navigation="home.navigation"
            :site="home.site"
            variant="overlay"
            theme="afa"
        />

        <section class="home-hero">
            <template v-if="activeHero">
                <div class="hero-layout home-container">
                    <div class="hero-copy">
                        <h1>{{ activeHero.title }}</h1>
                        <p v-if="activeHero.subtitle" class="hero-description">
                            {{ activeHero.subtitle }}
                        </p>
                        <div
                            v-if="
                                activeHero.button_label && activeHero.button_url
                            "
                            class="hero-actions"
                        >
                            <Link
                                :href="activeHero.button_url"
                                class="home-button home-button-primary"
                            >
                                {{ activeHero.button_label
                                }}<ArrowRight :size="18" aria-hidden="true" />
                            </Link>
                        </div>
                    </div>
                    <div class="hero-visual">
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
                                :style="{
                                    '--hero-desktop-focus': `${activeHero.desktop_focus?.x ?? 50}% ${activeHero.desktop_focus?.y ?? 50}%`,
                                    '--hero-mobile-focus': `${activeHero.mobile_focus?.x ?? 50}% ${activeHero.mobile_focus?.y ?? 50}%`,
                                }"
                                :src="
                                    activeHero.desktop_image_url ||
                                    activeHero.mobile_image_url ||
                                    ''
                                "
                                :alt="activeHero.title"
                                fetchpriority="high"
                            />
                        </picture>
                        <div v-else class="hero-placeholder" />
                        <div
                            v-if="home.hero_slides.length > 1"
                            class="hero-pagination"
                            aria-label="头图切换"
                        >
                            <button
                                v-for="(_slide, index) in home.hero_slides"
                                :key="index"
                                type="button"
                                class="hero-dot"
                                :class="{
                                    'is-active': activeHeroIndex === index,
                                }"
                                :aria-label="`切换到第 ${index + 1} 张头图`"
                                :aria-current="
                                    activeHeroIndex === index
                                        ? 'true'
                                        : undefined
                                "
                                @click="setHero(index)"
                            >
                                <span />
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <aside class="home-tools" aria-label="首页快捷工具">
            <SharePanel type="home" target-key="home" :title="home.site.name">
                <template #trigger="{ open, prepare }">
                    <button
                        type="button"
                        class="tool-button"
                        aria-label="分享网站"
                        aria-haspopup="dialog"
                        @pointerenter="prepare"
                        @focus="prepare"
                        @click="open"
                    >
                        <Share2 :size="20" aria-hidden="true" /><span
                            >分享</span
                        >
                    </button>
                </template>
            </SharePanel>
            <button
                v-if="showBackToTop"
                type="button"
                class="tool-button top-button"
                aria-label="返回顶部"
                @click="scrollToTop"
            >
                <ArrowUp :size="20" aria-hidden="true" /><span>顶部</span>
            </button>
        </aside>

        <section v-if="showCategoryModule" class="home-section album-section">
            <div class="home-container">
                <div class="section-heading">
                    <h2>{{ home.category_module.title }}</h2>
                </div>
                <div class="album-toolbar">
                    <div
                        ref="categoryNav"
                        class="category-scroll"
                        role="tablist"
                        aria-label="精选相册子分类导航"
                    >
                        <button
                            v-for="(tab, index) in home.category_module.tabs"
                            :key="`${tab.label}-${tab.category_id ?? index}`"
                            type="button"
                            class="category-tab"
                            :class="{
                                'is-active': activeCategoryIndex === index,
                            }"
                            role="tab"
                            :id="`home-album-tab-${index}`"
                            :aria-selected="activeCategoryIndex === index"
                            :tabindex="activeCategoryIndex === index ? 0 : -1"
                            aria-controls="home-album-results"
                            @keydown="categoryKey($event, index)"
                            @click="setCategoryTab(index)"
                        >
                            {{ tab.label }}
                        </button>
                        <span
                            class="category-marker"
                            aria-hidden="true"
                            :style="{
                                width: `${categoryMarker.width}px`,
                                transform: `translateX(${categoryMarker.left}px)`,
                            }"
                        />
                    </div>
                    <Link
                        :href="home.category_module.more_url"
                        class="section-link"
                        >更多<ArrowRight :size="18" aria-hidden="true"
                    /></Link>
                </div>
                <div
                    id="home-album-results"
                    role="tabpanel"
                    :aria-labelledby="`home-album-tab-${activeCategoryIndex}`"
                    tabindex="0"
                >
                    <div
                        v-if="activeCategoryTab?.items.length"
                        class="album-grid"
                    >
                        <Link
                            v-for="item in activeCategoryTab.items"
                            :key="item.id"
                            :href="item.url"
                            class="album-card"
                            @pointerenter="albumHover($event, true)"
                            @pointerleave="albumHover($event, false)"
                            :aria-label="`查看相册：${item.title}`"
                        >
                            <div class="album-image">
                                <img
                                    v-if="item.image_url"
                                    :src="item.image_url"
                                    :alt="item.alt"
                                    loading="lazy"
                                />
                                <div v-else class="image-placeholder" />
                                <div class="album-caption">
                                    <h3>{{ item.title }}</h3>
                                </div>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="home-empty">
                        当前分类还没有可公开展示的图片或相册。
                    </div>
                </div>
            </div>
        </section>

        <section v-if="showLatestPhotos" class="home-section latest-section">
            <div class="home-container">
                <div class="section-heading">
                    <h2>{{ home.latest_photos.title }}</h2>
                    <Link href="/photos" class="section-link"
                        >全部照片<ArrowRight :size="18" aria-hidden="true"
                    /></Link>
                </div>
                <div v-if="hasLatestPhotos" class="photo-grid">
                    <Link
                        v-for="item in home.latest_photos.items"
                        :key="item.id"
                        :href="item.url"
                        class="photo-card"
                        :aria-label="`查看图片：${item.title}`"
                    >
                        <div class="photo-image">
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.alt"
                                loading="lazy"
                            />
                            <div v-else class="image-placeholder" />
                            <span class="photo-hint" aria-hidden="true"
                                ><ArrowRight :size="18"
                            /></span>
                        </div>
                    </Link>
                </div>
                <div v-else class="home-empty">
                    最新照片模块已启用，当前还没有可公开展示的图片。
                </div>
            </div>
        </section>

        <section v-if="showTopics" class="home-section topic-section">
            <div class="home-container">
                <div class="section-heading">
                    <h2>{{ home.topic_module.title }}</h2>
                </div>
            </div>
            <div
                v-if="hasTopics"
                class="topic-carousel"
                role="region"
                aria-roledescription="轮播"
                :aria-label="home.topic_module.title"
            >
                <button
                    v-if="home.topic_module.items.length > 1"
                    type="button"
                    class="topic-arrow topic-prev"
                    aria-label="上一个专题"
                    @click="moveTopic(-1)"
                >
                    <ChevronLeft :size="22" aria-hidden="true" />
                </button>
                <div
                    ref="topicTrack"
                    class="topic-track"
                    @scroll.passive="syncTopic"
                    @pointerdown="beginManualTopicScroll"
                    @wheel.passive="beginManualTopicScroll"
                    @keydown.left.prevent="moveTopic(-1)"
                    @keydown.right.prevent="moveTopic(1)"
                >
                    <Link
                        v-for="(item, index) in home.topic_module.items"
                        :key="item.title"
                        :href="item.url"
                        class="topic-card"
                        :class="{ 'is-active': activeTopicIndex === index }"
                        :aria-current="
                            activeTopicIndex === index ? 'true' : undefined
                        "
                        @focus="centerTopic(index)"
                    >
                        <img
                            v-if="item.cover_image_url"
                            :src="item.cover_image_url"
                            :alt="item.title"
                            loading="lazy"
                        />
                        <div v-else class="image-placeholder" />
                        <div class="topic-caption">
                            <h3>{{ item.title }}</h3>
                            <span class="topic-entry" aria-hidden="true"
                                >进入专题<ArrowRight :size="18"
                            /></span>
                        </div>
                    </Link>
                </div>
                <button
                    v-if="home.topic_module.items.length > 1"
                    type="button"
                    class="topic-arrow topic-next"
                    aria-label="下一个专题"
                    @click="moveTopic(1)"
                >
                    <ChevronRight :size="22" aria-hidden="true" />
                </button>
            </div>
            <div v-else class="home-container">
                <div class="home-empty">
                    专题模块已启用，当前还没有可公开展示的专题配置。
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped src="./Welcome.css"></style>
