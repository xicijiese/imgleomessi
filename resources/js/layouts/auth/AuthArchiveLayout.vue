<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight, Bookmark, Images } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ title: string; description: string }>();

const page = usePage();
const site = computed(() => {
    const shell = page.props.publicShell as
        | { site: { name: string; logo_url: string | null } }
        | undefined;
    return shell?.site ?? { name: '梅西影像档案库', logo_url: null };
});
</script>

<template>
    <div class="auth-archive">
        <header class="auth-topbar">
            <Link
                href="/"
                class="auth-brand"
                :aria-label="`${site.name}，返回首页`"
            >
                <img
                    v-if="site.logo_url"
                    :src="site.logo_url"
                    :alt="site.name"
                    class="h-10 w-auto max-w-48 object-contain"
                />
                <template v-else>
                    <span class="auth-monogram" aria-hidden="true">M</span>
                    <span>{{ site.name }}</span>
                </template>
            </Link>
            <Link href="/" class="auth-back"
                ><ArrowLeft class="size-4" aria-hidden="true" />返回浏览</Link
            >
        </header>

        <main class="auth-body">
            <aside class="auth-story" aria-label="影像档案">
                <div class="auth-story-top">
                    <Images class="size-5" aria-hidden="true" /><span
                        >关于梅西的影像记忆</span
                    >
                </div>
                <div class="auth-story-content">
                    <h2>把喜欢的瞬间，<br />留在你的档案里。</h2>
                    <p>
                        从一场比赛到一段生涯，在影像中回看，<br
                            class="hidden xl:block"
                        />也为下一次重逢留个记号。
                    </p>
                    <div class="auth-archive-lines" aria-hidden="true">
                        <span>生涯足迹</span><span>赛场瞬间</span
                        ><span>珍藏记忆</span>
                    </div>
                </div>
                <div class="auth-story-bottom">
                    <Bookmark class="size-5 shrink-0" aria-hidden="true" />
                    <p>
                        登录后收藏图片、关注相册，<br />让每一次查阅都有自己的线索。
                    </p>
                </div>
            </aside>

            <section class="auth-form-panel" aria-labelledby="auth-title">
                <div class="auth-form-content">
                    <div class="auth-heading">
                        <h1 id="auth-title">{{ title }}</h1>
                        <p>{{ description }}</p>
                    </div>
                    <slot />
                    <div class="auth-browse-note">
                        <span>想先看看？公开影像无需登录。</span>
                        <Link href="/photos"
                            >浏览图库<ArrowUpRight
                                class="size-4"
                                aria-hidden="true"
                        /></Link>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.auth-archive {
    background: #fffffe;
    color: #094067;
    --foreground: #094067;
    --muted-foreground: #526b80;
    --primary: #094067;
    --primary-foreground: #fffffe;
    --input: #a6bed0;
    --ring: #147dc1;
    --destructive: #b42318;
    --background: #fffffe;
}
.auth-topbar {
    min-height: 88px;
    max-width: 1440px;
    margin: auto;
    padding: 20px 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}
.auth-brand {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    font-size: 17px;
    font-weight: 700;
}
.auth-monogram {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    background: #094067;
    color: #fffffe;
    border-radius: 6px;
    font-size: 22px;
}
.auth-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    white-space: nowrap;
}
.auth-body {
    display: grid;
    grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
    max-width: 1440px;
    margin: auto;
    min-height: calc(100svh - 88px);
}
.auth-story {
    background: #094067;
    color: #fffffe;
    padding: 48px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 64px;
}
.auth-story-top {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #c8e5fa;
    font-size: 14px;
}
.auth-story-content h2 {
    font-size: 38px;
    line-height: 1.55;
    font-weight: 650;
    letter-spacing: -0.025em;
    text-wrap: balance;
}
.auth-story-content > p {
    color: #c8e5fa;
    font-size: 15px;
    line-height: 1.9;
    margin-top: 24px;
}
.auth-archive-lines {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 24px;
    margin-top: 48px;
    padding-top: 24px;
    border-top: 1px solid #47718f;
    font-size: 13px;
    color: #c8e5fa;
}
.auth-story-bottom {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    color: #c8e5fa;
    font-size: 13px;
    line-height: 1.8;
}
.auth-form-panel {
    padding: 56px 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.auth-form-content {
    width: 100%;
    max-width: 408px;
}
.auth-heading {
    margin-bottom: 32px;
}
.auth-heading h1 {
    font-size: 30px;
    font-weight: 700;
    letter-spacing: -0.025em;
    line-height: 1.3;
}
.auth-heading p {
    margin-top: 12px;
    font-size: 14px;
    color: #526b80;
    line-height: 1.8;
}
.auth-browse-note {
    border-top: 1px solid #d6e3ec;
    margin-top: 32px;
    padding-top: 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
    font-size: 12px;
    color: #526b80;
}
.auth-browse-note a {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-weight: 600;
    color: #094067;
}
:deep(.auth-input) {
    height: 48px;
    border-radius: 6px;
    background: #fffffe;
    box-shadow: none;
    font-size: 16px;
    padding-left: 14px;
    padding-right: 14px;
}
:deep(.auth-input::placeholder) {
    color: #60768a;
}
:deep(.auth-submit) {
    height: 48px;
    border-radius: 6px;
    background: #094067;
    color: #fffffe;
    font-size: 15px;
    font-weight: 600;
    box-shadow: none;
}
:deep(.auth-submit:hover) {
    background: #126292;
}
:deep(.auth-link) {
    color: #094067;
    text-decoration-color: #a6bed0;
    text-underline-offset: 4px;
}
:deep(.auth-link:hover) {
    color: #126292;
    text-decoration: underline;
}
.auth-archive a:focus-visible {
    outline: 2px solid #147dc1;
    outline-offset: 5px;
    border-radius: 2px;
}
@media (min-width: 1440px) {
    .auth-body {
        min-height: 760px;
        margin-bottom: 48px;
    }
}
@media (max-width: 1023px) {
    .auth-topbar {
        padding: 20px 28px;
    }
    .auth-story {
        padding: 40px 28px;
    }
    .auth-story-content h2 {
        font-size: 30px;
    }
    .auth-form-panel {
        padding: 40px 28px;
    }
}
@media (max-width: 767px) {
    .auth-topbar {
        min-height: 76px;
        padding: 18px 20px;
    }
    .auth-brand {
        font-size: 14px;
    }
    .auth-back {
        font-size: 12px;
        gap: 4px;
    }
    .auth-body {
        display: block;
        min-height: auto;
    }
    .auth-story {
        padding: 24px;
        gap: 0;
    }
    .auth-story-top,
    .auth-story-content > p,
    .auth-archive-lines,
    .auth-story-bottom {
        display: none;
    }
    .auth-story-content h2 {
        font-size: 23px;
        line-height: 1.5;
    }
    .auth-form-panel {
        padding: 36px 24px 48px;
    }
    .auth-heading h1 {
        font-size: 26px;
    }
}
@media (prefers-reduced-motion: reduce) {
    :deep(*) {
        transition-duration: 0s !important;
        animation-duration: 0s !important;
    }
}
</style>
