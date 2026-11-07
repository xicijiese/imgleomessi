@vite('resources/css/page-transitions.css')
<script>
    (() => {
        let submitting = false;
        const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        // 仅处理整页导航；Inertia 与 Livewire 已阻止默认提交的操作不受影响。
        document.addEventListener('submit', event => {
            if (!event.defaultPrevented) submitting = true;
        });
        window.addEventListener('pageshow', () => { submitting = false; });
        window.addEventListener('pageswap', event => {
            if (!event.viewTransition) return;
            const target = event.activation?.entry?.url;
            if (!target || submitting || reduced() || new URL(target).pathname === location.pathname) {
                event.viewTransition.skipTransition();
            }
        });
        window.addEventListener('pagereveal', event => {
            const transition = event.viewTransition;
            if (!transition) return;
            if (reduced()) { transition.skipTransition(); return; }
            const timeout = setTimeout(() => transition.skipTransition(), 400);
            const clear = () => clearTimeout(timeout);
            transition.ready.then(clear, clear);
        });
    })();
</script>
