<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppOpenPicker from '@/components/AppOpenPicker.vue';
import Heading from '@/components/Heading.vue';
import WallpaperPicker from '@/components/WallpaperPicker.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Apariencia" />

    <h1 class="sr-only">Apariencia</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Apariencia"
            description="Elige cómo se ve bambudev en este dispositivo"
        />
        <AppearanceTabs />
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Fondo del Inicio"
            description="El fondo que ves detrás de tus apps en el Inicio"
        />
        <WallpaperPicker />
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="¿Cómo abren las apps?"
            description="Qué pasa cuando haces clic en una app del Inicio o de Herramientas"
        />
        <AppOpenPicker />
    </div>
</template>
