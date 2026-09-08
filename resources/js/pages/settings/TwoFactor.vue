<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { disable, enable } from '@/routes/two-factor';
import { Form, Head } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

interface Props {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <Head title="安全验证" />

    <SettingsLayout>
        <div class="space-y-6">
            <HeadingSmall
                title="安全验证"
                description="管理账号的两步验证设置"
            />

            <div
                v-if="!twoFactorEnabled"
                class="flex flex-col items-start justify-start space-y-4"
            >
                <Badge variant="destructive">未启用</Badge>

                <p class="text-muted-foreground">
                    启用两步验证后，登录时需要输入验证器应用生成的一次性验证码。
                    这可以在密码泄露时为账号增加一层保护。
                </p>

                <div>
                    <Button v-if="hasSetupData" @click="showSetupModal = true">
                        <ShieldCheck />继续设置
                    </Button>
                    <Form
                        v-else
                        v-bind="enable.form()"
                        @success="showSetupModal = true"
                        #default="{ processing }"
                    >
                        <Button type="submit" :disabled="processing">
                            <ShieldCheck />启用两步验证</Button
                        ></Form
                    >
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-start justify-start space-y-4"
            >
                <Badge variant="default">已启用</Badge>

                <p class="text-muted-foreground">
                    当前账号已启用两步验证。后续登录时，请使用验证器应用中的一次性验证码完成确认。
                </p>

                <TwoFactorRecoveryCodes />

                <div class="relative inline">
                    <Form v-bind="disable.form()" #default="{ processing }">
                        <Button
                            variant="destructive"
                            type="submit"
                            :disabled="processing"
                        >
                            <ShieldBan />
                            关闭两步验证
                        </Button>
                    </Form>
                </div>
            </div>

            <TwoFactorSetupModal
                v-model:isOpen="showSetupModal"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </div>
    </SettingsLayout>
</template>
