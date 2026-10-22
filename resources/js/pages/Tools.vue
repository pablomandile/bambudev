<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLaunchModal from '@/components/AppLaunchModal.vue';
import AppTile from '@/components/AppTile.vue';
import { useAppearance } from '@/composables/useAppearance';
import { usePortal } from '@/composables/usePortal';
import { matchesQuery } from '@/lib/portal';
import { help } from '@/routes';
import type { MicroApp } from '@/types';

const props = defineProps<{ apps: MicroApp[]; initialQuery: string }>();

const ALL = 'Todas';

const { resolvedAppearance } = useAppearance();
const { recents, showApp, genericIcons, setGenericIcons } = usePortal();

const query = ref(props.initialQuery);
const category = ref(ALL);

const categories = computed(() => {
    const names = [...new Set(props.apps.map((a) => a.cat))];

    return [
        { name: ALL, count: props.apps.length },
        ...names.map((name) => ({
            name,
            count: props.apps.filter((a) => a.cat === name).length,
        })),
    ];
});

const filtered = computed(() =>
    props.apps.filter(
        (a) =>
            (category.value === ALL || a.cat === category.value) &&
            matchesQuery(a, query.value),
    ),
);

const isRecent = (app: MicroApp) => recents.value.slice(0, 3).includes(app.id);
</script>

<template>
    <Head title="Herramientas" />

    <div
        class="mx-auto flex w-full max-w-[1180px] flex-col gap-8 px-4 pt-10 pb-24 sm:px-10 sm:pt-14"
    >
        <div class="flex flex-wrap items-end justify-between gap-8">
            <div class="max-w-[620px]">
                <div class="kicker mb-2.5">Catálogo</div>
                <h1 class="mb-3 text-[40px] tracking-[-0.03em] sm:text-[56px]">
                    Herramientas
                </h1>
                <p class="m-0 text-lg text-pretty text-bd-muted">
                    {{ apps.length }} apps (y contando) para que el día a día
                    pese un poco menos. Filtra, curiosea, abre.
                </p>
            </div>
            <label
                class="flex h-11 w-[min(340px,100%)] items-center gap-2.5 rounded border border-bd-divider bg-bd-surface px-3.5"
            >
                <i
                    class="ph-duotone ph-magnifying-glass text-[19px] text-bd-accent"
                />
                <input
                    v-model="query"
                    type="search"
                    aria-label="Filtrar herramientas"
                    placeholder="Filtrar herramientas"
                    class="min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none"
                />
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="c in categories"
                    :key="c.name"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-[2px] border px-3.5 py-[7px] text-sm"
                    :class="
                        category === c.name
                            ? 'border-bd-accent bg-bd-accent text-bd-bg'
                            : 'border-bd-divider text-bd-text hover:bg-bd-hover'
                    "
                    :aria-pressed="category === c.name"
                    @click="category = c.name"
                >
                    {{ c.name }}
                    <span class="text-xs opacity-60">{{ c.count }}</span>
                </button>
            </div>

            <label
                class="flex cursor-pointer items-center gap-2.5 text-sm text-bd-muted"
                title="Apágalo para ver el logo propio de cada app"
            >
                Iconos genéricos
                <button
                    type="button"
                    role="switch"
                    :aria-checked="genericIcons"
                    data-test="generic-icons-switch"
                    class="flex h-[22px] w-10 flex-none rounded-[11px] p-0.5 transition-colors duration-200"
                    :class="
                        genericIcons
                            ? 'justify-end bg-bd-accent'
                            : 'justify-start bg-bd-divider'
                    "
                    @click="setGenericIcons(!genericIcons)"
                >
                    <span
                        class="block size-[18px] rounded-full bg-white shadow-[0_1px_2px_rgba(0,0,0,.3)]"
                    />
                </button>
            </label>
        </div>

        <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-5">
            <div
                v-for="app in filtered"
                :key="app.id"
                class="flex flex-col gap-3.5 rounded bg-bd-surface p-[22px] transition-[box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:shadow-bd-md"
            >
                <div class="flex items-start justify-between">
                    <AppTile
                        :icon="app.icon"
                        :hue="app.hue"
                        :logo="app.logo"
                        :size="52"
                        :dark="resolvedAppearance === 'dark'"
                    />
                    <span v-if="isRecent(app)" class="tag tag-accent"
                        >Reciente</span
                    >
                </div>
                <div>
                    <div class="kicker mb-1 text-[10px]">{{ app.cat }}</div>
                    <div class="text-[21px] leading-[1.2] font-semibold">
                        {{ app.name }}
                    </div>
                </div>
                <p class="m-0 flex-1 text-sm text-pretty text-bd-muted">
                    {{ app.desc }}
                </p>
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="showApp(app)"
                    >
                        Abrir<i class="ph-duotone ph-arrow-up-right" />
                    </button>
                    <span class="text-xs text-bd-muted">En tu Inicio</span>
                </div>
            </div>
        </div>

        <p v-if="!filtered.length" class="text-xl text-bd-muted italic">
            Ninguna herramienta coincide. ¿Y si la inventamos?
            <Link :href="help()" class="bd-link">Sugiérela</Link>.
        </p>
    </div>

    <AppLaunchModal :apps="apps" />
</template>
