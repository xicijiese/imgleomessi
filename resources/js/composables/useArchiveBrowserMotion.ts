import { gsap } from 'gsap';
import { onMounted, onUnmounted, type Ref } from 'vue';

export function useArchiveBrowserMotion(root: Ref<HTMLElement | null>) {
    let motion: gsap.MatchMedia | undefined;
    onMounted(() => {
        if (!root.value) return;
        motion = gsap.matchMedia();
        motion.add(
            '(prefers-reduced-motion: no-preference)',
            () => {
                gsap.from('.browser-heading > *, .browser-quick-filters', {
                    y: 6,
                    duration: 0.24,
                    stagger: 0.04,
                    ease: 'power2.out',
                    clearProps: 'transform',
                });
            },
            root.value,
        );
    });
    onUnmounted(() => motion?.revert());
}
