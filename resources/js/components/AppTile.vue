<script setup lang="ts">
import { computed } from 'vue';
import { tileStyle } from '@/lib/portal';

const props = defineProps<{
    icon: string;
    hue: number;
    size: number | 'fluid';
    dark: boolean;
}>();

const style = computed(() => tileStyle(props.hue, props.size, props.dark));
</script>

<template>
    <span :style="style" aria-hidden="true">
        <i class="ph-duotone" :class="icon" />
    </span>
</template>
