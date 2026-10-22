<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-bd-bg p-6 text-bd-text md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-6">
                    <Link :href="home()" aria-label="bambudev, ir al Inicio">
                        <BrandMark />
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-[28px] tracking-[-0.02em]">
                            {{ title }}
                        </h1>
                        <p class="text-center text-[15px] text-bd-muted">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
