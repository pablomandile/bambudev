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
    <p
        class="m-0 flex items-center justify-center gap-1.5 text-sm whitespace-nowrap text-wall-muted"
        :title="`${sentence} Actualizado ${weather.updatedAt}.`"
        data-test="weather-line"
    >
        <span class="sr-only">{{ sentence }}</span>
        <span class="contents" aria-hidden="true">
            <i class="ph-duotone text-lg" :class="weather.icon" />
            <span class="font-semibold tabular-nums">{{ weather.temp }}°</span>
            <span>· {{ weather.label }}</span>
            <!-- En el celular no entra: queda en el tooltip y en la frase sr-only -->
            <span v-if="hasRange" class="hidden tabular-nums sm:inline"
                >· máx {{ weather.max }}° / mín {{ weather.min }}°</span
            >
            <span>· {{ weather.place }}</span>
        </span>
    </p>
</template>
