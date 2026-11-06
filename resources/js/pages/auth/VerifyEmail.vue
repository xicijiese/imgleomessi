<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout title="验证邮箱" description="请点击邮件中的链接完成邮箱验证。">
        <Head title="邮箱验证" />

        <div
            v-if="status === 'verification-link-sent'"
            class="auth-status"
            role="status"
        >
            新的验证链接已经发送到你注册时填写的邮箱。
        </div>

        <Form v-bind="send.form()" class="auth-form" v-slot="{ processing }">
            <Button
                class="auth-submit"
                :disabled="processing"
                :aria-busy="processing"
            >
                <Spinner v-if="processing" />
                重新发送验证邮件
            </Button>

            <div class="auth-secondary">
                <TextLink
                    :href="logout()"
                    as="button"
                    class="auth-secondary-action"
                >
                    退出登录
                </TextLink>
            </div>
        </Form>
    </AuthLayout>
</template>
