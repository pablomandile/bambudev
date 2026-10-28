<script setup lang="ts">
import { usePortal } from '@/composables/usePortal';
import type { AppOpenMode } from '@/types';

const { appOpen, setAppOpen } = usePortal();

const options: {
    id: AppOpenMode;
    icon: string;
    label: string;
    description: string;
}[] = [
    {
        id: 'new_tab',
        icon: 'ph-arrow-square-out',
        label: 'Directo, en una pestaña nueva',
        description:
            'Un clic y la app se abre aparte; bambudev queda abierto en esta pestaña.',
    },
    {
        id: 'ask',
        icon: 'ph-chat-centered-text',
        label: 'Preguntar antes',
        description:
            'Muestra una tarjeta con la app y, si confirmas, la abre en esta misma pestaña.',
    },
];
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Cómo abren las apps"
        class="grid gap-3 sm:grid-cols-2"
    >
        <button
            v-for="o in options"
            :key="o.id"
            type="button"
            role="radio"
            :aria-checked="o.id === appOpen"
            class="flex items-start gap-3 rounded-lg border p-3.5 text-left transition-colors"
            :class="
                o.id === appOpen
                    ? 'border-bd-accent ring-1 ring-bd-accent'
                    : 'border-bd-divider hover:bg-neutral-100 dark:hover:bg-neutral-800'
            "
            :data-test="`app-open-${o.id}`"
            @click="setAppOpen(o.id)"
        >
            <i
                class="ph-duotone mt-0.5 text-xl text-bd-accent"
                :class="o.icon"
                aria-hidden="true"
            />
            <span class="flex flex-col gap-1">
                <span class="text-sm font-medium">{{ o.label }}</span>
                <span class="text-xs text-muted-foreground">{{
                    o.description
                }}</span>
            </span>
        </button>
    </div>
</template>
