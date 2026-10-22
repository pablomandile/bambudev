<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { help } from '@/routes';

const pillars = [
    {
        title: 'Una sola puerta',
        text: 'Una cuenta, un inicio, todas tus apps. Nada de diez contraseñas para diez cosas.',
    },
    {
        title: 'Sin manual de instrucciones',
        text: 'Si una app necesita tutorial, la volvemos a hacer. Abrir y usar, así de simple.',
    },
    {
        title: 'Crece contigo',
        text: 'Cada tanto brota una app nueva. Aparece en tu inicio sin que tengas que instalar nada.',
    },
];
</script>

<template>
    <Head title="Acerca de" />

    <div
        class="mx-auto flex w-full max-w-[1180px] flex-col gap-16 px-4 pt-12 pb-28 sm:gap-[88px] sm:px-10 sm:pt-[72px]"
    >
        <div
            class="grid items-center gap-10 md:grid-cols-[minmax(0,7fr)_minmax(0,4fr)] md:gap-16"
        >
            <div>
                <div class="kicker mb-4">Acerca de bambudev</div>
                <h1
                    class="mb-7 text-[clamp(40px,5.4vw,72px)] leading-[1.02] tracking-[-0.035em] text-balance"
                >
                    Apps pequeñas para que los días grandes salgan bien.
                </h1>
                <p class="m-0 max-w-[580px] text-xl leading-[1.55] text-pretty">
                    Como el bambú: crece rápido, se dobla sin romperse y siempre
                    suma un tallo más. bambudev reúne herramientas sencillas
                    para las cosas de todos los días — la plata, la salud, la
                    casa, la mascota, la agenda — detrás de una sola puerta.
                </p>
            </div>
            <div
                class="mx-auto grid aspect-square w-full max-w-[420px] place-items-center overflow-hidden rounded bg-white"
            >
                <img
                    src="/images/bambudev-wordmark.png"
                    alt="bambudev"
                    class="w-[118%] max-w-none"
                />
            </div>
        </div>

        <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-12">
            <div v-for="(p, i) in pillars" :key="p.title">
                <div
                    class="mb-2.5 text-[13px] font-semibold text-bd-accent-700"
                >
                    0{{ i + 1 }}
                </div>
                <h3 class="mb-2.5 text-[25px]">{{ p.title }}</h3>
                <p class="m-0 text-pretty text-bd-muted">{{ p.text }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end justify-between gap-8">
            <blockquote
                class="m-0 max-w-[760px] text-[clamp(26px,3vw,38px)] leading-[1.25] text-balance italic"
            >
                «Lo difícil no debería ser encontrar la herramienta, sino elegir
                qué hacer con el tiempo que te ahorra.»
            </blockquote>
            <Link :href="help()" class="btn btn-primary px-5 py-3 text-base">
                ¿Tienes una idea? Cuéntanosla
                <i class="ph-duotone ph-arrow-right" />
            </Link>
        </div>
    </div>
</template>
