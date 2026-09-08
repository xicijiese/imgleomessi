<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, LogOut, Settings, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.flushAll();
};

const page = usePage();
const canAccessAdmin = computed(() => Boolean(page.props.canAccessAdmin));

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" href="/me" prefetch as="button">
                <UserRound class="mr-2 h-4 w-4" />
                个人中心
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="canAccessAdmin" :as-child="true">
            <a class="block w-full" href="/admin">
                <LayoutDashboard class="mr-2 h-4 w-4" />
                管理后台
            </a>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit()" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                账号设置
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            退出登录
        </Link>
    </DropdownMenuItem>
</template>
