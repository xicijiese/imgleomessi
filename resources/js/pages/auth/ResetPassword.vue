<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { update } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <AuthLayout title="重置密码" description="请输入新的账号密码">
        <Head title="重置密码" />

        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="auth-form"
        >
            <div class="auth-fields">
                <div class="auth-field">
                    <Label for="email">邮箱地址</Label>
                    <Input
                        id="email"
                        :aria-invalid="Boolean(errors.email)"
                        :aria-describedby="
                            errors.email ? 'email-error' : undefined
                        "
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-model="inputEmail"
                        class="auth-input"
                        readonly
                    />
                    <InputError
                        id="email-error"
                        class="auth-error"
                        :message="errors.email"
                        role="alert"
                    />
                </div>

                <div class="auth-field">
                    <Label for="password">新密码</Label>
                    <PasswordInput
                        id="password"
                        label="新密码"
                        :aria-invalid="Boolean(errors.password)"
                        :aria-describedby="
                            errors.password ? 'password-error' : undefined
                        "
                        name="password"
                        autocomplete="new-password"
                        autofocus
                        placeholder="请输入新密码"
                    />
                    <InputError
                        id="password-error"
                        class="auth-error"
                        :message="errors.password"
                        role="alert"
                    />
                </div>

                <div class="auth-field">
                    <Label for="password_confirmation">确认密码</Label>
                    <PasswordInput
                        id="password_confirmation"
                        :aria-invalid="Boolean(errors.password_confirmation)"
                        :aria-describedby="
                            errors.password_confirmation
                                ? 'password_confirmation-error'
                                : undefined
                        "
                        label="确认密码"
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="请再次输入新密码"
                    />
                    <InputError
                        id="password_confirmation-error"
                        class="auth-error"
                        :message="errors.password_confirmation"
                        role="alert"
                    />
                </div>

                <Button
                    type="submit"
                    class="auth-submit"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" />
                    重置密码
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
