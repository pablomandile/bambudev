<script setup lang="ts">
import { usePortal } from '@/composables/usePortal';
import { WALLPAPERS } from '@/lib/portal';
import type { Wallpaper } from '@/types';

const { wallpaper, setWallpaper } = usePortal();

// La muestra es chica: los fondos con dibujo se achican para que se reconozcan.
const previewSize: Partial<Record<Wallpaper, string>> = {
    trama: 'background-size: 6px 6px, auto',
    esterilla: 'background-size: auto, 180px auto, auto',
    canizo: 'background-size: auto, 180px auto, auto',
};
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Fondo del Inicio"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3"
    >
        <button
            v-for="w in WALLPAPERS"
            :key="w.id"
            type="button"
            role="radio"
            :aria-checked="w.id === wallpaper"
            class="flex flex-col gap-2 rounded-lg p-1.5 text-left text-sm transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
            :data-test="`wallpaper-${w.id}`"
            @click="setWallpaper(w.id)"
        >
            <span
                class="block h-16 w-full rounded-md ring-offset-2 ring-offset-background"
                :class="[
                    `wall-${w.id}`,
                    w.id === wallpaper
                        ? 'ring-2 ring-bd-accent'
                        : 'ring-1 ring-bd-divider',
                ]"
                :style="previewSize[w.id]"
                aria-hidden="true"
            />
            <span class="flex items-center gap-1.5 px-0.5">
                <i
                    v-if="w.id === wallpaper"
                    class="ph-duotone ph-check-circle text-bd-accent"
                />
                {{ w.label }}
            </span>
        </button>
    </div>
</template>
