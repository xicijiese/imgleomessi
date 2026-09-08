<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
</script>

<template>
    <Head title="外观设置" />

    <SettingsLayout>
        <div class="space-y-6">
            <HeadingSmall
                title="外观设置"
                description="调整账号页面的显示偏好"
            />
            <AppearanceTabs />
        </div>
    </SettingsLayout>
</template>
