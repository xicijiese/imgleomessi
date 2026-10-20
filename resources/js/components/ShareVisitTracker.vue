<script setup lang="ts">
import { shareRequest } from '@/lib/sharing';
import { usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, watch } from 'vue';
const page = usePage();
let timer: ReturnType<typeof setTimeout> | null = null;
let generation = 0;
let visit: { id: string; receipt: string } | null = null;
let busy = false;
let needsReset = false;
let activePath = '';
function clear() {
    generation++;
    if (timer) clearTimeout(timer);
    timer = null;
    visit = null;
}
async function tick(visible: boolean, current: number) {
    if (
        !visit ||
        busy ||
        current !== generation ||
        location.pathname !== activePath
    )
        return;
    busy = true;
    visible = visible && !needsReset;
    needsReset = false;
    try {
        const result = await shareRequest<{ done: boolean; receipt?: string }>(
            `/sharing/visits/${visit.id}/tick`,
            { receipt: visit.receipt, visible },
        );
        if (current !== generation) return;
        if (result.done) {
            clear();
            return;
        }
        if (result.receipt && visit) visit.receipt = result.receipt;
    } catch {
        clear();
    } finally {
        busy = false;
    }
    if (current === generation && visit)
        timer = setTimeout(
            () => tick(document.visibilityState === 'visible', current),
            2000,
        );
}
async function start() {
    clear();
    const current = generation;
    const code = new URL(location.href).searchParams.get('via');
    if (!code || !/^[a-zA-Z0-9]{24}$/.test(code)) return;
    activePath = location.pathname;
    try {
        const result = await shareRequest<{ id: string; receipt: string }>(
            '/sharing/visits',
            { code, path: activePath },
        );
        if (current !== generation) return;
        visit = result;
        timer = setTimeout(
            () => tick(document.visibilityState === 'visible', current),
            2000,
        );
    } catch {
        /* 统计异常不阻塞公开浏览。 */
    }
}
function visibility() {
    needsReset = true;
    if (timer) clearTimeout(timer);
    // 可见状态切换先重置节拍，恢复前台时不计入后台经过时间。
    void tick(false, generation);
}
onMounted(() => {
    void start();
    document.addEventListener('visibilitychange', visibility);
});
watch(
    () => page.url,
    () => void start(),
);
onUnmounted(() => {
    clear();
    document.removeEventListener('visibilitychange', visibility);
});
</script>
<template><span hidden aria-hidden="true"></span></template>
