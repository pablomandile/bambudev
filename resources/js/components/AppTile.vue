<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePortal } from '@/composables/usePortal';
import { tileStyle } from '@/lib/portal';

const props = defineProps<{
    icon: string;
    hue: number;
    size: number | 'fluid';
    dark: boolean;
    logo?: string | null;
}>();

const { genericIcons } = usePortal();

// Si el logo no carga, vuelve al icono genérico en vez de mostrar una imagen rota.
const logoFailed = ref(false);
watch(
    () => props.logo,
    () => (logoFailed.value = false),
);

const showLogo = computed(
    () => !genericIcons.value && !!props.logo && !logoFailed.value,
);

const style = computed(() => tileStyle(props.hue, props.size, props.dark));

// El logo ocupa el mismo cuadrado que el icono, sin el fondo de color.
const logoStyle = computed(() => ({
    width: style.value.width,
    height: style.value.height,
    flex: 'none',
    borderRadius: '4px',
    objectFit: 'contain' as const,
}));
</script>

<template>
    <img
        v-if="showLogo"
        :src="logo!"
        alt=""
        :style="logoStyle"
        loading="lazy"
        @error="logoFailed = true"
    />
    <span v-else :style="style" aria-hidden="true">
        <i class="ph-duotone" :class="icon" />
    </span>
</template>
