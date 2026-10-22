<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { login, logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

const page = usePage();
const user = computed(() => page.props.auth.user);
const firstName = computed(() => user.value?.name.split(' ')[0] ?? '');

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <Link
        v-if="!user"
        :href="login()"
        class="inline-flex h-9 items-center gap-1.5 rounded-[2px] border border-bd-divider px-3 text-sm font-semibold text-bd-text hover:bg-bd-hover"
    >
        <i class="ph-duotone ph-sign-in text-lg" />
        <span class="max-lg:sr-only">Ingresar</span>
    </Link>
    <DropdownMenu v-else>
        <DropdownMenuTrigger
            class="inline-flex h-9 max-w-40 items-center gap-1.5 rounded-[2px] border border-bd-divider px-3 text-sm font-semibold text-bd-text hover:bg-bd-hover"
        >
            <i class="ph-duotone ph-user-circle flex-none text-lg" />
            <span class="truncate max-lg:sr-only">{{ firstName }}</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel class="font-normal">
                <div class="truncate font-semibold">{{ user.name }}</div>
                <div class="truncate text-xs text-bd-muted">
                    {{ user.email }}
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link :href="editProfile()" class="w-full cursor-pointer">
                    <i class="ph-duotone ph-gear text-base" />
                    Configuración
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
                <Link
                    :href="logout()"
                    as="button"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                    @click="handleLogout"
                >
                    <i class="ph-duotone ph-sign-out text-base" />
                    Cerrar sesión
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
