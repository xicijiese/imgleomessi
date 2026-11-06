<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout
        title="确认密码"
        description="这是安全操作区域，请先确认密码后继续。"
    >
        <Head title="确认密码" />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="auth-form"
        >
            <div class="auth-fields">
                <div class="auth-field">
                    <Label for="password">密码</Label>
                    <PasswordInput
                        id="password"
                        :aria-invalid="Boolean(errors.password)"
                        :aria-describedby="
                            errors.password ? 'password-error' : undefined
                        "
                        name="password"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError
                        id="password-error"
                        class="auth-error"
                        :message="errors.password"
                        role="alert"
                    />
                </div>

                <div class="flex items-center">
                    <Button
                        class="auth-submit"
                        :disabled="processing"
                        :aria-busy="processing"
                        data-test="confirm-password-button"
                    >
                        <Spinner v-if="processing" />
                        确认密码
                    </Button>
                </div>
            </div>
        </Form>
    </AuthLayout>
</template>
