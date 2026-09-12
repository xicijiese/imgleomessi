<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage();
const user = page.props.auth.user;

const avatarInput = ref<HTMLInputElement | null>(null);
const cropViewport = ref<HTMLElement | null>(null);
const cropImageElement = ref<HTMLImageElement | null>(null);
const cropSourceUrl = ref<string | null>(null);
const avatarPreviewUrl = ref<string | null>(null);
const selectedAvatarName = ref('');
const fileError = ref('');
const isCropDialogOpen = ref(false);
const isCropping = ref(false);
const removeAvatar = ref(false);
const cropViewportSize = ref(320);
const naturalImageWidth = ref(0);
const naturalImageHeight = ref(0);
const cropZoom = ref(1);
const cropPosition = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const dragOrigin = ref({ x: 0, y: 0 });

const renderedImageScale = computed(() => {
    if (!naturalImageWidth.value || !naturalImageHeight.value) {
        return 1;
    }

    return (
        Math.max(
            cropViewportSize.value / naturalImageWidth.value,
            cropViewportSize.value / naturalImageHeight.value,
        ) * cropZoom.value
    );
});

const renderedImageWidth = computed(
    () => naturalImageWidth.value * renderedImageScale.value,
);
const renderedImageHeight = computed(
    () => naturalImageHeight.value * renderedImageScale.value,
);

const cropImageStyle = computed(() => ({
    width: `${renderedImageWidth.value}px`,
    height: `${renderedImageHeight.value}px`,
    transform: `translate3d(${cropPosition.value.x}px, ${cropPosition.value.y}px, 0)`,
}));

const displayedAvatarUrl = computed(() =>
    removeAvatar.value ? null : avatarPreviewUrl.value || user.avatar,
);

function clampCropPosition(value: number, renderedSize: number): number {
    const minimum = cropViewportSize.value - renderedSize;

    return Math.min(0, Math.max(minimum, value));
}

function centerCropImage(): void {
    cropPosition.value = {
        x: (cropViewportSize.value - renderedImageWidth.value) / 2,
        y: (cropViewportSize.value - renderedImageHeight.value) / 2,
    };
}

async function measureCropViewport(): Promise<void> {
    await nextTick();

    const bounds = cropViewport.value?.getBoundingClientRect();

    if (bounds?.width) {
        cropViewportSize.value = bounds.width;
        centerCropImage();
    }
}

function handleCropImageLoad(event: Event): void {
    const image = event.currentTarget as HTMLImageElement;

    cropImageElement.value = image;
    naturalImageWidth.value = image.naturalWidth;
    naturalImageHeight.value = image.naturalHeight;
    void measureCropViewport();
}

function updateCropZoom(event: Event): void {
    const nextZoom = Number((event.target as HTMLInputElement).value);
    const previousScale = renderedImageScale.value;
    const focusX =
        (cropViewportSize.value / 2 - cropPosition.value.x) / previousScale;
    const focusY =
        (cropViewportSize.value / 2 - cropPosition.value.y) / previousScale;

    cropZoom.value = nextZoom;
    cropPosition.value = {
        x: clampCropPosition(
            cropViewportSize.value / 2 - focusX * renderedImageScale.value,
            renderedImageWidth.value,
        ),
        y: clampCropPosition(
            cropViewportSize.value / 2 - focusY * renderedImageScale.value,
            renderedImageHeight.value,
        ),
    };
}

function startCropDrag(event: PointerEvent): void {
    if (!cropSourceUrl.value) {
        return;
    }

    isDragging.value = true;
    dragStart.value = { x: event.clientX, y: event.clientY };
    dragOrigin.value = { ...cropPosition.value };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function moveCropDrag(event: PointerEvent): void {
    if (!isDragging.value) {
        return;
    }

    cropPosition.value = {
        x: clampCropPosition(
            dragOrigin.value.x + event.clientX - dragStart.value.x,
            renderedImageWidth.value,
        ),
        y: clampCropPosition(
            dragOrigin.value.y + event.clientY - dragStart.value.y,
            renderedImageHeight.value,
        ),
    };
}

function endCropDrag(): void {
    isDragging.value = false;
}

function revokeCropSource(): void {
    if (cropSourceUrl.value) {
        URL.revokeObjectURL(cropSourceUrl.value);
    }

    cropSourceUrl.value = null;
    cropImageElement.value = null;
    naturalImageWidth.value = 0;
    naturalImageHeight.value = 0;
}

function discardPendingCrop(): void {
    if (avatarInput.value) {
        avatarInput.value.value = '';
    }

    revokeCropSource();
    cropZoom.value = 1;
    cropPosition.value = { x: 0, y: 0 };
    selectedAvatarName.value = '';
}

function handleCropDialogChange(open: boolean): void {
    isCropDialogOpen.value = open;

    if (!open && cropSourceUrl.value) {
        discardPendingCrop();
    }
}

function handleAvatarSelection(event: Event): void {
    const input = event.currentTarget as HTMLInputElement;
    const file = input.files?.[0];

    fileError.value = '';

    if (!file) {
        return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        fileError.value = '头像只支持 JPG、PNG 或 WebP 图片。';
        input.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        fileError.value = '头像文件不能超过 2MB。';
        input.value = '';
        return;
    }

    removeAvatar.value = false;
    revokeCropSource();
    cropSourceUrl.value = URL.createObjectURL(file);
    selectedAvatarName.value = file.name;
    cropZoom.value = 1;
    cropPosition.value = { x: 0, y: 0 };
    isCropDialogOpen.value = true;
}

async function confirmCrop(): Promise<void> {
    if (
        !cropImageElement.value ||
        !naturalImageWidth.value ||
        isCropping.value
    ) {
        return;
    }

    isCropping.value = true;

    try {
        const outputSize = 512;
        const sourceSize = cropViewportSize.value / renderedImageScale.value;
        const sourceX = Math.max(
            0,
            Math.min(
                naturalImageWidth.value - sourceSize,
                -cropPosition.value.x / renderedImageScale.value,
            ),
        );
        const sourceY = Math.max(
            0,
            Math.min(
                naturalImageHeight.value - sourceSize,
                -cropPosition.value.y / renderedImageScale.value,
            ),
        );
        const canvas = document.createElement('canvas');

        canvas.width = outputSize;
        canvas.height = outputSize;

        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('无法创建头像裁剪画布。');
        }

        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(
            cropImageElement.value,
            sourceX,
            sourceY,
            sourceSize,
            sourceSize,
            0,
            0,
            outputSize,
            outputSize,
        );

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.92),
        );

        if (!blob) {
            throw new Error('头像裁剪失败，请重新选择图片。');
        }

        const croppedFile = new File([blob], `avatar-${Date.now()}.jpg`, {
            type: 'image/jpeg',
        });
        const dataTransfer = new DataTransfer();

        dataTransfer.items.add(croppedFile);

        if (!avatarInput.value) {
            throw new Error('找不到头像文件控件，请刷新页面后重试。');
        }

        avatarInput.value.files = dataTransfer.files;

        if (avatarPreviewUrl.value) {
            URL.revokeObjectURL(avatarPreviewUrl.value);
        }

        avatarPreviewUrl.value = URL.createObjectURL(croppedFile);
        selectedAvatarName.value = '已裁剪头像.jpg';
        revokeCropSource();
        cropZoom.value = 1;
        cropPosition.value = { x: 0, y: 0 };
        isCropDialogOpen.value = false;
    } catch (error) {
        fileError.value =
            error instanceof Error ? error.message : '头像裁剪失败，请重试。';
    } finally {
        isCropping.value = false;
    }
}

onBeforeUnmount(() => {
    revokeCropSource();

    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
    }
});
</script>

<template>
    <Head title="个人资料" />

    <SettingsLayout>
        <div class="flex flex-col space-y-6">
            <HeadingSmall
                title="个人资料"
                description="更新你的头像、昵称、手机号和邮箱地址"
            />

            <Form
                v-bind="ProfileController.update.form()"
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-3">
                    <Label for="avatar">头像</Label>
                    <div class="flex items-center gap-4">
                        <Avatar class="h-16 w-16 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="displayedAvatarUrl"
                                :src="displayedAvatarUrl"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-[#d8eefe] text-[#094067]"
                                >{{
                                    user.name.slice(0, 1).toUpperCase()
                                }}</AvatarFallback
                            >
                        </Avatar>
                        <div class="grid gap-2">
                            <input
                                id="avatar-file"
                                ref="avatarInput"
                                class="peer sr-only"
                                type="file"
                                name="avatar"
                                accept="image/jpeg,image/png,image/webp"
                                @change="handleAvatarSelection"
                            />
                            <div class="flex flex-wrap items-center gap-3">
                                <label
                                    for="avatar-file"
                                    class="inline-flex h-10 cursor-pointer items-center justify-center rounded-md bg-[#3da9fc] px-4 text-sm font-medium text-[#fffffe] transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-[#3da9fc]/40 peer-focus-visible:ring-offset-2 hover:bg-[#094067]"
                                >
                                    选择头像文件
                                </label>
                                <span
                                    v-if="selectedAvatarName"
                                    class="max-w-60 truncate text-sm text-foreground"
                                >
                                    {{ selectedAvatarName }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                支持 JPG、PNG、WebP，最大
                                2MB。选择图片后可裁剪为正方形。
                            </p>
                            <p
                                v-if="fileError"
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {{ fileError }}
                            </p>
                            <label
                                v-if="user.avatar"
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <input
                                    v-model="removeAvatar"
                                    type="checkbox"
                                    name="remove_avatar"
                                    value="1"
                                />
                                移除当前头像
                            </label>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="errors.avatar" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">昵称</Label>
                    <Input
                        id="name"
                        class="mt-1 block w-full border-[#90b4ce]/60 bg-[#fffffe] text-[#094067] shadow-none placeholder:text-[#5f6c7b]/60 focus-visible:border-[#3da9fc] focus-visible:ring-[#3da9fc]/30 dark:border-[#90b4ce]/60 dark:bg-[#fffffe] dark:text-[#094067] dark:placeholder:text-[#5f6c7b]/60"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="请输入昵称"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">邮箱地址</Label>
                    <Input
                        id="email"
                        type="email"
                        class="mt-1 block w-full border-[#90b4ce]/60 bg-[#fffffe] text-[#094067] shadow-none placeholder:text-[#5f6c7b]/60 focus-visible:border-[#3da9fc] focus-visible:ring-[#3da9fc]/30 dark:border-[#90b4ce]/60 dark:bg-[#fffffe] dark:text-[#094067] dark:placeholder:text-[#5f6c7b]/60"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="请输入邮箱地址"
                    />
                    <InputError class="mt-2" :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">手机号码</Label>
                    <Input
                        id="phone"
                        type="tel"
                        class="mt-1 block w-full border-[#90b4ce]/60 bg-[#fffffe] text-[#094067] shadow-none placeholder:text-[#5f6c7b]/60 focus-visible:border-[#3da9fc] focus-visible:ring-[#3da9fc]/30 dark:border-[#90b4ce]/60 dark:bg-[#fffffe] dark:text-[#094067] dark:placeholder:text-[#5f6c7b]/60"
                        name="phone"
                        :default-value="user.phone ?? undefined"
                        autocomplete="tel"
                        placeholder="请输入 11 位手机号码"
                    />
                    <InputError class="mt-2" :message="errors.phone" />
                </div>
                <div v-if="mustVerifyEmail && !user.email_verified_at">
                    <p class="-mt-4 text-sm text-muted-foreground">
                        你的邮箱地址尚未验证。
                        <Link
                            :href="send()"
                            as="button"
                            class="text-[#094067] underline decoration-[#90b4ce] underline-offset-4 transition-colors duration-300 ease-out hover:text-[#3da9fc] hover:decoration-[#3da9fc]"
                        >
                            点击重新发送验证邮件。
                        </Link>
                    </p>

                    <div
                        v-if="status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600"
                    >
                        新的验证邮件已发送。
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        class="bg-[#3da9fc] text-[#fffffe] hover:bg-[#094067] focus-visible:ring-[#3da9fc]/40"
                        :disabled="processing"
                        data-test="update-profile-button"
                        >保存资料</Button
                    >

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-neutral-600"
                        >
                            已保存。
                        </p>
                    </Transition>
                </div>
            </Form>
        </div>

        <Dialog :open="isCropDialogOpen" @update:open="handleCropDialogChange">
            <DialogContent
                class="border-[#90b4ce]/35 bg-[#fffffe] text-[#094067] sm:max-w-lg dark:border-[#90b4ce]/35 dark:bg-[#fffffe] dark:text-[#094067]"
            >
                <DialogHeader>
                    <DialogTitle>裁剪头像</DialogTitle>
                    <DialogDescription class="text-[#5f6c7b]">
                        拖动图片调整位置，使用缩放控制画面，裁剪区域会保持正方形。
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-5">
                    <div
                        ref="cropViewport"
                        class="relative mx-auto aspect-square w-full max-w-[320px] cursor-grab touch-none overflow-hidden rounded-lg bg-[#094067] active:cursor-grabbing"
                        @pointerdown="startCropDrag"
                        @pointermove="moveCropDrag"
                        @pointerup="endCropDrag"
                        @pointercancel="endCropDrag"
                    >
                        <img
                            v-if="cropSourceUrl"
                            ref="cropImageElement"
                            :src="cropSourceUrl"
                            alt="头像裁剪预览"
                            class="pointer-events-none absolute top-0 left-0 max-w-none select-none"
                            :style="cropImageStyle"
                            draggable="false"
                            @load="handleCropImageLoad"
                        />
                        <div
                            class="pointer-events-none absolute inset-0 ring-1 ring-white/80 ring-inset"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="grid gap-2">
                        <div
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <Label for="avatar-zoom">缩放</Label>
                            <span class="text-muted-foreground"
                                >{{ cropZoom.toFixed(2) }}x</span
                            >
                        </div>
                        <input
                            id="avatar-zoom"
                            class="h-2 w-full cursor-pointer accent-[#3da9fc]"
                            type="range"
                            min="1"
                            max="3"
                            step="0.01"
                            :value="cropZoom"
                            aria-label="头像缩放"
                            @input="updateCropZoom"
                        />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-[#90b4ce]/60 bg-[#fffffe] text-[#094067] hover:bg-[#d8eefe] hover:text-[#094067] dark:border-[#90b4ce]/60 dark:bg-[#fffffe] dark:text-[#094067]"
                        @click="handleCropDialogChange(false)"
                    >
                        取消
                    </Button>
                    <Button
                        type="button"
                        class="bg-[#3da9fc] text-[#fffffe] hover:bg-[#094067] focus-visible:ring-[#3da9fc]/40"
                        :disabled="isCropping"
                        @click="confirmCrop"
                    >
                        {{ isCropping ? '处理中...' : '确定裁剪' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <DeleteUser />
    </SettingsLayout>
</template>
