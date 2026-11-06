<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const { appearance, updateAppearance } = useAppearance();
const systemDark = ref(false);
let systemQuery: MediaQueryList | undefined;
const syncSystem = () => {
    systemDark.value = systemQuery?.matches ?? false;
};
onMounted(() => {
    systemQuery = window.matchMedia('(prefers-color-scheme: dark)');
    syncSystem();
    systemQuery.addEventListener('change', syncSystem);
});
onUnmounted(() => systemQuery?.removeEventListener('change', syncSystem));
const labels = { light: '浅色', dark: '深色', system: '跟随系统' };
const effectiveMode = computed(() =>
    appearance.value === 'system'
        ? systemDark.value
            ? 'dark'
            : 'light'
        : appearance.value,
);
const tabs = [
    { value: 'light', label: '浅色', description: '使用明亮的界面' },
    { value: 'dark', label: '深色', description: '使用深色的界面' },
    { value: 'system', label: '跟随系统', description: '按系统显示偏好切换' },
] as const;
</script>
<template>
    <section class="appearance-panel">
        <fieldset>
            <legend>显示模式</legend>
            <p id="appearance-help" class="mode-help">
                选择后立即生效，偏好保存在当前浏览器。
            </p>
            <div class="mode-options">
                <label
                    v-for="tab in tabs"
                    :key="tab.value"
                    class="mode-option"
                    :data-selected="appearance === tab.value"
                >
                    <div
                        class="mode-preview"
                        :data-preview="tab.value"
                        :data-effective="
                            tab.value === 'system'
                                ? systemDark
                                    ? 'dark'
                                    : 'light'
                                : undefined
                        "
                        aria-hidden="true"
                    >
                        <div class="mini-header">梅西影像档案库</div>
                        <div class="mini-layout">
                            <div class="mini-nav">
                                <span>个人资料</span><span>外观设置</span>
                            </div>
                            <div class="mini-content">
                                <strong>显示偏好</strong
                                ><span>让阅读更舒适</span>
                            </div>
                        </div>
                    </div>
                    <div class="option-title">
                        <input
                            type="radio"
                            name="appearance"
                            :value="tab.value"
                            :checked="appearance === tab.value"
                            aria-describedby="appearance-help"
                            @change="updateAppearance(tab.value)"
                        /><strong>{{ tab.label }}</strong>
                    </div>
                    <p>{{ tab.description }}</p>
                </label>
            </div>
        </fieldset>
        <div class="current-mode">
            <p id="current-mode" role="status" aria-live="polite">
                当前选择：{{ labels[appearance] }}
            </p>
            <p id="effective-mode">
                {{
                    appearance === 'system'
                        ? `当前系统为${systemDark ? '深色' : '浅色'}，将随系统变化。`
                        : `自适应界面使用${effectiveMode === 'dark' ? '深色' : '浅色'}显示。`
                }}
            </p>
        </div>
        <p class="brand-note">部分页面保留本站品牌配色，不随显示模式切换。</p>
    </section>
</template>
<style scoped src="./AppearanceTabs.css"></style>
