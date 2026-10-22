<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import InstallAppButton from '@/components/InstallAppButton.vue';
import { Toaster } from '@/components/ui/sonner';
import UserMenu from '@/components/UserMenu.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { about, help, home, tools } from '@/routes';
import { index as adminApps } from '@/routes/admin/apps';

// Las páginas de configuración pasan breadcrumbs como prop del layout; acá no se usan.
defineProps<{ breadcrumbs?: unknown }>();

const page = usePage();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { resolvedAppearance, updateAppearance } = useAppearance();

const tabs = [
    { label: 'Inicio', icon: 'ph-house', href: home() },
    { label: 'Herramientas', icon: 'ph-squares-four', href: tools() },
    { label: 'Acerca de', icon: 'ph-plant', href: about() },
    { label: 'Ayuda', icon: 'ph-lifebuoy', href: help() },
];

const isDark = computed(() => resolvedAppearance.value === 'dark');
const isAdmin = computed(() => page.props.auth.isAdmin);
const adminActive = computed(() => isCurrentOrParentUrl('/admin'));

const toggleTheme = () => updateAppearance(isDark.value ? 'light' : 'dark');
</script>

<template>
    <div class="flex h-dvh flex-col overflow-hidden bg-bd-bg text-bd-text">
        <header
            class="relative z-[5] flex h-[68px] flex-none items-center gap-4 border-b border-bd-divider bg-bd-bg px-4 min-[900px]:gap-9 min-[900px]:px-8"
        >
            <Link :href="home()" aria-label="bambudev, ir al Inicio">
                <BrandMark />
            </Link>

            <nav
                class="hidden h-full items-stretch gap-0.5 md:flex"
                aria-label="Principal"
            >
                <Link
                    v-for="tab in tabs"
                    :key="tab.label"
                    :href="tab.href"
                    class="flex h-full items-center gap-2 border-y-[3px] border-t-transparent px-2.5 text-[15px] font-semibold transition-colors min-[900px]:px-4"
                    :class="
                        isCurrentUrl(tab.href)
                            ? 'border-b-bd-accent text-bd-text'
                            : 'border-b-transparent text-bd-muted hover:text-bd-text'
                    "
                    :aria-current="isCurrentUrl(tab.href) ? 'page' : undefined"
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <InstallAppButton />
                <button
                    type="button"
                    class="btn btn-secondary btn-icon"
                    title="Cambiar modo"
                    :aria-label="
                        isDark ? 'Pasar a modo claro' : 'Pasar a modo oscuro'
                    "
                    @click="toggleTheme"
                >
                    <i
                        class="ph-duotone text-[19px]"
                        :class="isDark ? 'ph-sun' : 'ph-moon'"
                    />
                </button>
                <Link
                    v-if="isAdmin"
                    :href="adminApps()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-[2px] border border-bd-divider px-3 text-sm font-semibold"
                    :class="
                        adminActive
                            ? 'bg-bd-text text-bd-bg'
                            : 'text-bd-text hover:bg-bd-hover'
                    "
                >
                    <i class="ph-duotone ph-gear-six text-lg" />
                    <span class="max-lg:sr-only">Admin</span>
                </Link>
                <UserMenu />
            </div>
        </header>

        <main class="relative flex min-h-0 flex-1 flex-col overflow-auto">
            <slot />
        </main>

        <nav
            class="grid h-16 flex-none grid-cols-4 border-t border-bd-divider bg-bd-bg md:hidden"
            aria-label="Principal"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.label"
                :href="tab.href"
                class="flex min-w-0 flex-col items-center justify-center gap-0.5 border-t-[3px] text-xs font-semibold"
                :class="
                    isCurrentUrl(tab.href)
                        ? 'border-t-bd-accent text-bd-text'
                        : 'border-t-transparent text-bd-muted'
                "
                :aria-current="isCurrentUrl(tab.href) ? 'page' : undefined"
            >
                <i class="ph-duotone text-[22px]" :class="tab.icon" />
                <span class="max-w-full truncate">{{ tab.label }}</span>
            </Link>
        </nav>

        <Toaster position="bottom-center" :mobile-offset="{ bottom: '80px' }" />
    </div>
</template>
