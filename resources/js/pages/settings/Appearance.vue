<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
</script>

<template>
    <Head title="外观设置" />

    <SettingsLayout variant="archive">
        <div class="appearance-content">
            <div class="appearance-heading browser-heading">
                <div>
                    <h1>外观设置</h1>
                    <p>选择适合自己的显示模式。</p>
                </div>
            </div>
            <AppearanceTabs />
        </div>
    </SettingsLayout>
</template>

<style scoped src="./Appearance.css"></style>
