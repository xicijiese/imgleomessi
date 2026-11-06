<script setup lang="ts">
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Form, Head } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Head title="修改密码" />

    <SettingsLayout variant="archive">
        <div class="password-content">
            <div class="password-heading browser-heading">
                <div>
                    <h1>修改密码</h1>
                    <p>建议使用足够长且不重复的密码保护账号。</p>
                </div>
            </div>

            <Form
                v-bind="PasswordController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="password-panel"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="password-field">
                    <Label for="current_password">当前密码</Label>
                    <input
                        id="current_password"
                        :aria-invalid="!!errors.current_password"
                        aria-describedby="current_password-error"
                        name="current_password"
                        type="password"
                        class="password-input"
                        autocomplete="current-password"
                        placeholder="请输入当前密码"
                    />
                    <InputError
                        id="current_password-error"
                        :message="errors.current_password"
                    />
                </div>

                <div class="new-password-fields">
                    <div class="password-field">
                        <Label for="password">新密码</Label>
                        <input
                            id="password"
                            :aria-invalid="!!errors.password"
                            aria-describedby="password-error"
                            name="password"
                            type="password"
                            class="password-input"
                            autocomplete="new-password"
                            placeholder="请输入新密码"
                        />
                        <InputError
                            id="password-error"
                            :message="errors.password"
                        />
                    </div>

                    <div class="password-field">
                        <Label for="password_confirmation">确认新密码</Label>
                        <input
                            id="password_confirmation"
                            :aria-invalid="!!errors.password_confirmation"
                            aria-describedby="password_confirmation-error"
                            name="password_confirmation"
                            type="password"
                            class="password-input"
                            autocomplete="new-password"
                            placeholder="请再次输入新密码"
                        />
                        <InputError
                            id="password_confirmation-error"
                            :message="errors.password_confirmation"
                        />
                    </div>
                </div>
                <div class="password-save-row">
                    <Button
                        class="password-save"
                        :disabled="processing"
                        data-test="update-password-button"
                        >{{ processing ? '保存中…' : '保存密码' }}</Button
                    >

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            role="status"
                            aria-live="polite"
                            class="password-feedback"
                        >
                            已保存。
                        </p>
                    </Transition>
                </div>
            </Form>
        </div>
    </SettingsLayout>
</template>

<style scoped src="./Password.css"></style>
