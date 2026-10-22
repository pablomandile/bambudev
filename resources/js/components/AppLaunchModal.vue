<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import AppTile from '@/components/AppTile.vue';
import { useAppearance } from '@/composables/useAppearance';
import { usePortal } from '@/composables/usePortal';
import type { MicroApp } from '@/types';

const props = defineProps<{ apps: MicroApp[] }>();

const { openId, closeApp, launch } = usePortal();
const { resolvedAppearance } = useAppearance();

const app = computed(() => props.apps.find((a) => a.id === openId.value));

const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && openId.value) {
        closeApp();
    }
};

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    closeApp();
});
</script>

<template>
    <div
        v-if="app"
        class="fixed inset-0 z-50 grid place-items-center bg-[rgba(18,20,18,0.5)] p-5"
        @click.self="closeApp"
    >
        <div
            role="dialog"
            aria-modal="true"
            :aria-label="app.name"
            class="flex w-[min(440px,100%)] flex-col gap-[18px] rounded bg-bd-surface p-7 text-bd-text shadow-bd-lg"
        >
            <div class="flex items-center gap-4">
                <AppTile
                    :icon="app.icon"
                    :hue="app.hue"
                    :size="64"
                    :dark="resolvedAppearance === 'dark'"
                />
                <div>
                    <div class="kicker text-[10px]">{{ app.cat }}</div>
                    <div class="text-[26px] leading-[1.15] font-semibold">
                        {{ app.name }}
                    </div>
                </div>
            </div>
            <p class="m-0 text-base text-pretty text-bd-muted">
                {{ app.desc }}
            </p>
            <div class="flex flex-wrap justify-end gap-2.5">
                <button
                    type="button"
                    class="btn btn-secondary"
                    @click="closeApp"
                >
                    Ahora no
                </button>
                <button
                    v-if="app.hasTarget"
                    type="button"
                    class="btn btn-primary"
                    autofocus
                    @click="launch(app)"
                >
                    Abrir {{ app.name }}
                    <i class="ph-duotone ph-arrow-up-right" />
                </button>
                <button v-else type="button" class="btn btn-primary" disabled>
                    Muy pronto
                </button>
            </div>
        </div>
    </div>
</template>
