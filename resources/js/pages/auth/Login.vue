<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/auth/AuthArchiveLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="欢迎回来"
        description="登录账号，继续收藏与查阅你的影像档案。"
    >
        <Head title="登录" />

        <div v-if="status" role="status" class="auth-status">
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="auth-form"
        >
            <div class="auth-fields">
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
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="请输入邮箱地址"
                    />
                    <InputError
                        class="auth-error"
                        id="email-error"
                        :message="errors.email"
                        role="alert"
                    />
                </div>

                <div class="auth-field">
                    <div class="auth-field-heading">
                        <Label for="password">密码</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="auth-link text-sm"
                        >
                            忘记密码？
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        :aria-invalid="Boolean(errors.password)"
                        :aria-describedby="
                            errors.password ? 'password-error' : undefined
                        "
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="请输入密码"
                    />
                    <InputError
                        class="auth-error"
                        id="password-error"
                        :message="errors.password"
                        role="alert"
                    />
                </div>

                <div class="auth-field-heading">
                    <Label for="remember" class="remember">
                        <Checkbox id="remember" name="remember" />
                        <span>保持登录</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="auth-submit"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? '正在登录…' : '登录' }}
                </Button>
            </div>

            <div class="auth-foot" v-if="canRegister">
                还没有账号？
                <TextLink
                    :href="register()"
                    class="auth-link ml-1 font-semibold underline"
                    >创建账号</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
