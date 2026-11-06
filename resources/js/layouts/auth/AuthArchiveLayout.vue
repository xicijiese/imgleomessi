<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps<{ title?: string; description?: string }>();

const page = usePage();
const root = ref<HTMLElement | null>(null);
const site = computed(() => {
    const shell = page.props.publicShell as
        | { site: { name: string; logo_url: string | null } }
        | undefined;
    return shell?.site ?? { name: '梅西影像档案库', logo_url: null };
});

let motion: gsap.MatchMedia | undefined;
onMounted(() => {
    if (!root.value) return;
    motion = gsap.matchMedia();
    motion.add(
        '(prefers-reduced-motion: no-preference)',
        () => {
            gsap.from('.auth-card', {
                y: 6,
                duration: 0.24,
                ease: 'power2.out',
                clearProps: 'transform',
            });
        },
        root.value,
    );
});
onUnmounted(() => motion?.revert());
</script>

<template>
    <div ref="root" class="auth-archive">
        <header class="auth-header">
            <div class="auth-header-inner">
                <Link
                    href="/"
                    class="auth-brand"
                    :aria-label="`${site.name}，返回首页`"
                >
                    <img
                        v-if="site.logo_url"
                        :src="site.logo_url"
                        :alt="site.name"
                    />
                    <span v-else class="auth-monogram" aria-hidden="true"
                        >M</span
                    >
                    <strong>{{ site.name }}</strong>
                </Link>
                <Link href="/" class="auth-back">返回浏览</Link>
            </div>
        </header>
        <main class="auth-main">
            <section class="auth-card" aria-labelledby="auth-title">
                <h1 id="auth-title">{{ title }}</h1>
                <p class="auth-description">{{ description }}</p>
                <slot />
            </section>
            <div class="auth-guest">
                <p>公开影像无需登录。</p>
                <Link href="/photos">浏览图库</Link>
            </div>
        </main>
    </div>
</template>

<style scoped src="./AuthArchiveLayout.css"></style>
