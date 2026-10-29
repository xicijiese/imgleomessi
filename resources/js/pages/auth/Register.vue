<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/auth/AuthArchiveLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase
        title="创建你的账号"
        description="加入影像档案库，收藏那些值得回看的瞬间。"
    >
        <Head title="注册" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="name">昵称</Label>
                    <Input
                        class="auth-input"
                        id="name"
                        :aria-invalid="Boolean(errors.name)"
                        :aria-describedby="
                            errors.name ? 'name-error' : undefined
                        "
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        name="name"
                        placeholder="请输入昵称"
                    />
                    <InputError
                        id="name-error"
                        :message="errors.name"
                        role="alert"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="email">邮箱地址</Label>
                    <Input
                        class="auth-input"
                        id="email"
                        :aria-invalid="Boolean(errors.email)"
                        :aria-describedby="
                            errors.email ? 'email-error' : undefined
                        "
                        type="email"
                        required
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError
                        id="email-error"
                        :message="errors.email"
                        role="alert"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">手机号码</Label>
                    <Input
                        class="auth-input"
                        id="phone"
                        :aria-invalid="Boolean(errors.phone)"
                        :aria-describedby="
                            errors.phone ? 'phone-error' : undefined
                        "
                        type="tel"
                        required
                        autocomplete="tel"
                        inputmode="tel"
                        maxlength="11"
                        name="phone"
                        placeholder="请输入 11 位手机号码"
                    />
                    <InputError
                        id="phone-error"
                        :message="errors.phone"
                        role="alert"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password">密码</Label>
                    <PasswordInput
                        id="password"
                        :aria-invalid="Boolean(errors.password)"
                        :aria-describedby="
                            errors.password ? 'password-error' : undefined
                        "
                        required
                        autocomplete="new-password"
                        name="password"
                        placeholder="设置登录密码"
                    />
                    <InputError
                        id="password-error"
                        :message="errors.password"
                        role="alert"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">确认密码</Label>
                    <PasswordInput
                        id="password_confirmation"
                        :aria-invalid="Boolean(errors.password_confirmation)"
                        :aria-describedby="
                            errors.password_confirmation
                                ? 'password_confirmation-error'
                                : undefined
                        "
                        required
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="请再次输入密码"
                    />
                    <InputError
                        id="password_confirmation-error"
                        :message="errors.password_confirmation"
                        role="alert"
                    />
                </div>

                <Button
                    type="submit"
                    class="auth-submit mt-2 w-full"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? '正在创建…' : '创建账号' }}
                </Button>
            </div>

            <div
                class="rounded-md bg-[#eef6fc] px-4 py-4 text-center text-sm text-[#526b80]"
            >
                已有账号？
                <TextLink
                    :href="login()"
                    class="auth-link font-semibold underline underline-offset-4"
                    >登录</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
