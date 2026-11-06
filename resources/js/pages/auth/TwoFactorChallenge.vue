<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/two-factor/login';
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AuthConfigContent {
    title: string;
    description: string;
    toggleText: string;
}

const authConfigContent = computed<AuthConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: '恢复代码',
            description: '请输入一个紧急恢复代码来确认账号访问权限。',
            toggleText: '使用动态验证码登录',
        };
    }

    return {
        title: '动态验证码',
        description: '请输入身份验证器应用提供的动态验证码。',
        toggleText: '使用恢复代码登录',
    };
});

const showRecoveryInput = ref<boolean>(false);

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const code = ref<string>('');
</script>

<template>
    <AuthLayout
        :title="authConfigContent.title"
        :description="authConfigContent.description"
    >
        <Head title="二次验证" />

        <div class="auth-fields">
            <template v-if="!showRecoveryInput">
                <Form
                    v-bind="store.form()"
                    class="auth-form"
                    reset-on-error
                    @error="code = ''"
                    #default="{ errors, processing, clearErrors }"
                >
                    <input type="hidden" name="code" :value="code" />
                    <div
                        class="auth-field"
                        :class="{ 'auth-otp-invalid': errors.code }"
                    >
                        <Label for="otp">动态验证码</Label>
                        <InputOTP
                            id="otp"
                            class="auth-otp"
                            aria-label="六位动态验证码"
                            :aria-invalid="Boolean(errors.code)"
                            :aria-describedby="
                                errors.code ? 'code-error' : undefined
                            "
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError
                            id="code-error"
                            class="auth-error"
                            :message="errors.code"
                            role="alert"
                        />
                    </div>
                    <Button
                        type="submit"
                        class="auth-submit"
                        :disabled="processing"
                        :aria-busy="processing"
                        >{{ processing ? '正在验证…' : '继续' }}</Button
                    >
                    <div class="auth-secondary">
                        <button
                            type="button"
                            class="auth-secondary-action w-full"
                            @click="() => toggleRecoveryMode(clearErrors)"
                        >
                            {{ authConfigContent.toggleText }}
                        </button>
                    </div>
                </Form>
            </template>

            <template v-else>
                <Form
                    v-bind="store.form()"
                    class="auth-form"
                    reset-on-error
                    #default="{ errors, processing, clearErrors }"
                >
                    <div class="auth-field">
                        <Label for="recovery_code">恢复代码</Label>
                        <Input
                            id="recovery_code"
                            class="auth-input"
                            :aria-invalid="Boolean(errors.recovery_code)"
                            :aria-describedby="
                                errors.recovery_code
                                    ? 'recovery-code-error'
                                    : undefined
                            "
                            name="recovery_code"
                            type="text"
                            placeholder="请输入恢复代码"
                            :autofocus="showRecoveryInput"
                            required
                        />
                        <InputError
                            id="recovery-code-error"
                            class="auth-error"
                            :message="errors.recovery_code"
                            role="alert"
                        />
                    </div>
                    <Button
                        type="submit"
                        class="auth-submit"
                        :disabled="processing"
                        :aria-busy="processing"
                        >{{ processing ? '正在验证…' : '继续' }}</Button
                    >

                    <div class="auth-secondary">
                        <button
                            type="button"
                            class="auth-secondary-action w-full"
                            @click="() => toggleRecoveryMode(clearErrors)"
                        >
                            {{ authConfigContent.toggleText }}
                        </button>
                    </div>
                </Form>
            </template>
        </div>
    </AuthLayout>
</template>
