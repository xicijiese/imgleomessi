<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

withDefaults(defineProps<{ variant?: 'default' | 'archive' }>(), {
    variant: 'default',
});

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div :class="variant === 'archive' ? 'profile-danger' : 'space-y-6'">
        <HeadingSmall
            v-if="variant !== 'archive'"
            title="删除账号"
            description="删除你的账号和相关账号数据"
        />
        <div
            :class="
                variant === 'archive'
                    ? 'danger-row'
                    : 'space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10'
            "
        >
            <div v-if="variant === 'archive'" class="danger-copy">
                <h2>删除账号</h2>
                <p>删除账号及相关账号数据。该操作不可撤销，请谨慎处理。</p>
            </div>
            <div
                v-else
                class="relative space-y-0.5 text-red-600 dark:text-red-100"
            >
                <p class="font-medium">危险操作</p>
                <p class="text-sm">该操作不可撤销，请谨慎处理。</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        :class="{
                            'archive-delete-trigger': variant === 'archive',
                        }"
                        data-test="delete-user-button"
                        >删除账号</Button
                    >
                </DialogTrigger>
                <DialogContent
                    :class="{ 'archive-delete-dialog': variant === 'archive' }"
                >
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.$el?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>确认删除账号？</DialogTitle>
                            <DialogDescription>
                                账号删除后，相关账号数据也会被删除且无法恢复。
                                请输入密码确认你要继续执行该操作。
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">密码</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                ref="passwordInput"
                                class="border-[#90b4ce]/60 bg-[#fffffe] text-[#094067] shadow-none placeholder:text-[#5f6c7b]/60 focus-visible:border-[#3da9fc] focus-visible:ring-[#3da9fc]/30 dark:border-[#90b4ce]/60 dark:bg-[#fffffe] dark:text-[#094067] dark:placeholder:text-[#5f6c7b]/60"
                                placeholder="请输入密码"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    取消
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                确认删除
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>

<style scoped>
.profile-danger {
    border-top: 1px solid #dbdbea;
    margin-top: 28px;
    padding-top: 24px;
    color: #042240;
}
.danger-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
}
.danger-copy h2 {
    font-size: 18px;
    font-weight: 600;
    line-height: 1.5;
}
.danger-copy p {
    font-size: 13px;
    color: #526779;
    line-height: 1.8;
    margin-top: 8px;
}
.archive-delete-trigger {
    flex-shrink: 0;
    min-height: 44px;
    border: 1px solid #ce9191;
    background: #fff;
    color: #a33434;
    box-shadow: none;
}
.archive-delete-trigger:hover {
    background: #fff1f1;
}
.archive-delete-dialog {
    width: calc(100% - 32px);
    max-width: 520px;
    max-height: 90dvh;
    overflow: auto;
    background: #fff;
    color: #042240;
    border-color: #dbdbea;
}
.archive-delete-dialog :deep(input) {
    min-height: 44px;
    font-size: 16px;
    background: #fff;
    color: #042240;
    border-color: #a8bfd0;
}
.archive-delete-dialog :deep(button) {
    min-height: 44px;
}
.archive-delete-dialog :deep([data-slot='dialog-description']) {
    color: #526779;
}
.archive-delete-dialog :deep([data-test='confirm-delete-user-button']) {
    background: #a33434;
    color: #fff;
}
.profile-danger :deep(button:focus-visible) {
    outline: 2px solid #042240;
    outline-offset: 3px;
}
@media (max-width: 767px) {
    .danger-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
}
</style>
