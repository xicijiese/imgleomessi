import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { onMounted, onUnmounted, watch, type Ref } from 'vue';

gsap.registerPlugin(ScrollTrigger);

export function useGalleryScrollReveal(
    root: Ref<HTMLElement | null>,
    items: () => unknown,
    selector = '.browser-photo-grid > *',
) {
    const revealed = new WeakSet<Element>();
    let motion: gsap.MatchMedia | undefined;
    let resize: ResizeObserver | undefined;
    let stopWatching: (() => void) | undefined;
    const setup = () => {
        motion?.revert();
        if (!root.value) return;
        motion = gsap.matchMedia();
        motion.add(
            '(prefers-reduced-motion: no-preference)',
            (context) => {
                const cards = gsap.utils.toArray<HTMLElement>(
                    selector,
                    root.value,
                );
                // 首屏和恢复滚动位置以上的内容立即可见，只准备尚未进入视口的卡片。
                cards
                    .filter(
                        (card) =>
                            card.getBoundingClientRect().top <
                            window.innerHeight,
                    )
                    .forEach((card) => revealed.add(card));
                const pending = cards.filter(
                    (card) =>
                        !revealed.has(card) &&
                        card.getBoundingClientRect().top >= window.innerHeight,
                );
                if (!pending.length) return;
                const reveal = (batch: Element[]) =>
                    context.add(() => {
                        batch.forEach((card) => revealed.add(card));
                        gsap.to(batch, {
                            opacity: 1,
                            y: 0,
                            duration: 0.35,
                            stagger: 0.04,
                            ease: 'power2.out',
                            overwrite: true,
                            clearProps: 'opacity,transform',
                        });
                    });
                ScrollTrigger.batch(pending, {
                    start: 'clamp(top 98%)',
                    once: true,
                    interval: 0.04,
                    batchMax: 4,
                    onEnter: reveal,
                    onEnterBack: reveal,
                });
                gsap.set(pending, {
                    opacity: 0,
                    y: window.matchMedia('(max-width: 767px)').matches ? 8 : 12,
                });
            },
            root.value,
        );
    };
    onMounted(() => {
        setup();
        stopWatching = watch(items, setup, { flush: 'post' });
        resize = new ResizeObserver(() => ScrollTrigger.refresh(true));
        if (root.value) resize.observe(root.value);
    });
    onUnmounted(() => {
        stopWatching?.();
        resize?.disconnect();
        motion?.revert();
    });
}
