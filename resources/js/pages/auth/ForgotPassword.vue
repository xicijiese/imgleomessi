<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="找回密码"
        description="输入邮箱后，我们会发送密码重置链接"
    >
        <Head title="找回密码" />

        <div v-if="status" class="auth-status" role="status">
            {{ status }}
        </div>

        <div class="auth-fields">
            <Form
                v-bind="email.form()"
                v-slot="{ errors, processing }"
                class="auth-form"
            >
                <div class="auth-field">
                    <Label for="email">邮箱地址</Label>
                    <Input
                        class="auth-input"
                        id="email"
                        :aria-invalid="Boolean(errors.email)"
                        :aria-describedby="
                            errors.email ? 'email-error' : undefined
                        "
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="请输入邮箱地址"
                    />
                    <InputError
                        id="email-error"
                        class="auth-error"
                        :message="errors.email"
                        role="alert"
                    />
                </div>

                <div class="auth-secondary">
                    <Button
                        class="auth-submit"
                        :disabled="processing"
                        :aria-busy="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        发送密码重置链接
                    </Button>
                </div>
            </Form>

            <div class="auth-foot">
                <span>或者返回</span>
                <TextLink :href="login()" class="auth-link">登录</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
