<script setup lang="ts">
import { computed } from 'vue';
import type { Weather } from '@/types';

const props = defineProps<{ weather: Weather }>();

const hasRange = computed(
    () => props.weather.max !== null && props.weather.min !== null,
);

// La frase completa, para lectores de pantalla y el tooltip.
const sentence = computed(() => {
    const w = props.weather;
    const range = hasRange.value ? `, máxima ${w.max}°, mínima ${w.min}°` : '';

    return `En ${w.place}: ${w.temp}°, ${w.label.toLowerCase()}${range}. Sensación térmica ${w.feelsLike}°.`;
});
</script>

<template>
    <div
        class="flex items-center justify-center gap-2.5 whitespace-nowrap sm:justify-end"
        :title="`${sentence} Actualizado ${weather.updatedAt}.`"
        data-test="weather-line"
    >
        <span class="sr-only">{{ sentence }}</span>
        <i
            class="ph-duotone text-[34px] leading-none"
            :class="weather.icon"
            aria-hidden="true"
        />
        <span
            class="text-[26px] leading-none font-semibold tracking-[-0.02em] tabular-nums"
            aria-hidden="true"
            >{{ weather.temp }}°</span
        >
        <span class="flex flex-col text-left leading-tight" aria-hidden="true">
            <span class="text-sm">{{ weather.label }}</span>
            <span class="text-xs text-wall-muted tabular-nums">
                <template v-if="hasRange"
                    >máx {{ weather.max }}° / mín {{ weather.min }}° ·
                </template>
                {{ weather.place }}
            </span>
        </span>
    </div>
</template>
