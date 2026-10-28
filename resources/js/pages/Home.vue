<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLaunchModal from '@/components/AppLaunchModal.vue';
import AppTile from '@/components/AppTile.vue';
import WeatherLine from '@/components/WeatherLine.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useClock } from '@/composables/useClock';
import { usePortal } from '@/composables/usePortal';
import { WALLPAPERS, matchesQuery } from '@/lib/portal';
import { tools } from '@/routes';
import type { MicroApp, Weather } from '@/types';

const props = defineProps<{ apps: MicroApp[]; weather?: Weather | null }>();

// En el celular entran 4×2 apps por página; desde sm, 6×2 como en el diseño.
const isPhone = useMediaQuery('(max-width: 639px)');
const perPage = computed(() => (isPhone.value ? 8 : 12));

const page = usePage();
const { time, date, salutation } = useClock();
const { resolvedAppearance } = useAppearance();
const { recents, wallpaper, openId, openApp, isOnHome, setOnHome } =
    usePortal();

const firstName = computed(() => page.props.auth.user?.name.split(' ')[0]);
const greeting = computed(() =>
    firstName.value
        ? `${salutation.value}, ${firstName.value}.`
        : `${salutation.value}.`,
);

const wallDark = computed(
    () =>
        resolvedAppearance.value === 'dark' ||
        !!WALLPAPERS.find((w) => w.id === wallpaper.value)?.dark,
);

// — Inicio personalizado —
const ADD = 'add';
const editing = ref(false);

const homeApps = computed(() => props.apps.filter((a) => isOnHome(a)));

const toggleEditing = () => {
    editing.value = !editing.value;
    query.value = '';
};

// — Búsqueda y paginación —
const query = ref('');
const current = ref(0);
const searchInput = ref<HTMLInputElement | null>(null);

// La búsqueda recorre todo el catálogo, también lo que no está en el Inicio.
const results = computed(() =>
    query.value
        ? props.apps.filter((a) => matchesQuery(a, query.value))
        : homeApps.value,
);

const pages = computed(() => {
    const items: (MicroApp | typeof ADD)[] = editing.value
        ? [...results.value, ADD]
        : results.value;
    const chunks: (MicroApp | typeof ADD)[][] = [];

    for (let i = 0; i < items.length; i += perPage.value) {
        chunks.push(items.slice(i, i + perPage.value));
    }

    return chunks.length ? chunks : [[]];
});

const activePage = computed(() =>
    Math.min(current.value, pages.value.length - 1),
);

watch(query, () => {
    current.value = 0;

    // Buscando se ve todo el catálogo: no tiene sentido seguir editando.
    if (query.value) {
        editing.value = false;
    }
});

const goPage = (delta: number) => {
    current.value = Math.max(
        0,
        Math.min(pages.value.length - 1, activePage.value + delta),
    );
};

const onSearchKey = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && results.value[0]) {
        openApp(results.value[0]);
    }

    if (e.key === 'Escape') {
        query.value = '';
        (e.target as HTMLInputElement).blur();
    }
};

// Swipe / arrastre del puntero de más de 50px.
let pointerX: number | null = null;
const onPointerDown = (e: PointerEvent) => (pointerX = e.clientX);
const onPointerUp = (e: PointerEvent) => {
    if (pointerX === null) {
        return;
    }

    const dx = e.clientX - pointerX;
    pointerX = null;

    if (Math.abs(dx) > 50) {
        goPage(dx < 0 ? 1 : -1);
    }
};

const onKeydown = (e: KeyboardEvent) => {
    if (openId.value) {
        return;
    }

    const target = e.target as HTMLElement;
    const typing = /INPUT|TEXTAREA|SELECT/.test(target.tagName);

    if (typing || target.isContentEditable) {
        return;
    }

    if (e.key === 'Escape' && editing.value) {
        editing.value = false;
    } else if (e.key === '/') {
        e.preventDefault();
        searchInput.value?.focus();
    } else if (e.key === 'ArrowRight') {
        goPage(1);
    } else if (e.key === 'ArrowLeft') {
        goPage(-1);
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

// — Barra inferior —
const recentApps = computed(() =>
    recents.value
        .map((id) => props.apps.find((a) => a.id === id))
        .filter((a): a is MicroApp => !!a)
        .slice(0, isPhone.value ? 2 : 4),
);
</script>

<template>
    <Head title="Inicio" />

    <div
        class="wall relative flex h-full min-h-0 flex-col overflow-hidden"
        :class="`wall-${wallpaper}`"
    >
        <!-- Arriba a la derecha (en el celular, a todo el ancho): búsqueda y el tiempo -->
        <div
            class="flex flex-none justify-end px-4 pt-[clamp(10px,2vh,20px)] sm:px-8"
        >
            <div
                class="flex w-full flex-col items-stretch gap-[clamp(4px,1vh,10px)] sm:w-[340px]"
            >
                <label
                    class="flex h-11 w-full items-center gap-2.5 rounded border border-bd-divider bg-bd-surface px-4 text-bd-text shadow-bd-sm"
                >
                    <i
                        class="ph-duotone ph-magnifying-glass text-xl text-bd-accent"
                    />
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="search"
                        aria-label="Buscar una app"
                        placeholder="Busca una app…"
                        class="min-w-0 flex-1 border-0 bg-transparent text-base outline-none placeholder:text-[color-mix(in_srgb,var(--bd-text)_65%,transparent)] [&::-webkit-search-cancel-button]:hidden"
                        @keydown="onSearchKey"
                    />
                    <kbd
                        class="hidden rounded-[2px] border border-bd-divider px-[7px] py-px font-serif text-xs text-bd-muted sm:inline"
                        >/</kbd
                    >
                </label>

                <!-- Alto fijo: nada se corre cuando llega el tiempo, ni si no llega -->
                <div class="h-9">
                    <Deferred data="weather">
                        <template #fallback />
                        <WeatherLine v-if="weather" :weather="weather" />
                    </Deferred>
                </div>
            </div>
        </div>

        <div
            class="flex min-h-0 flex-1 flex-col items-center justify-center-safe gap-[clamp(8px,2.2vh,30px)] px-4 pb-1 sm:px-8"
        >
            <div class="flex w-full flex-col items-center gap-1.5 text-center">
                <div
                    class="text-[clamp(40px,8.5vh,104px)] leading-[0.95] font-semibold tracking-[-0.04em] tabular-nums"
                >
                    {{ time }}
                </div>
                <div
                    class="text-base tracking-[0.02em] whitespace-nowrap text-wall-muted [@media(max-height:600px)]:hidden"
                >
                    {{ date }}
                </div>
                <div
                    class="mt-[clamp(0px,0.6vh,6px)] text-[clamp(17px,2.6vh,22px)] leading-[1.3] italic [@media(max-height:700px)]:hidden"
                >
                    {{ greeting }}
                    <span class="text-wall-muted">¿Qué resolvemos hoy?</span>
                </div>
            </div>

            <div
                class="relative w-[min(760px,100%)] flex-none touch-pan-y overflow-hidden"
                @pointerdown="onPointerDown"
                @pointerup="onPointerUp"
                @pointercancel="pointerX = null"
            >
                <div
                    class="flex h-full transition-transform duration-[450ms] ease-[cubic-bezier(.2,.8,.2,1)]"
                    :style="{ transform: `translateX(-${activePage * 100}%)` }"
                >
                    <div
                        v-for="(pageApps, i) in pages"
                        :key="i"
                        class="grid flex-[0_0_100%] grid-cols-4 gap-x-2 gap-y-[clamp(4px,1.4vh,16px)] sm:grid-cols-6"
                        :inert="i !== activePage"
                    >
                        <template
                            v-for="item in pageApps"
                            :key="item === ADD ? ADD : item.id"
                        >
                            <Link
                                v-if="item === ADD"
                                :href="tools()"
                                class="flex flex-col items-center justify-start gap-2.5 rounded px-0.5 py-1.5"
                                data-test="home-add"
                            >
                                <span
                                    class="grid size-[clamp(54px,10vh,84px)] place-items-center rounded border-2 border-dashed border-current text-[clamp(22px,4vh,34px)] opacity-60"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-plus" />
                                </span>
                                <span
                                    class="max-w-full text-center text-xs leading-[1.2] sm:text-sm"
                                    >Agregar</span
                                >
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="flex flex-col items-center justify-start gap-2.5 rounded px-0.5 py-1.5 transition-transform duration-[180ms]"
                                :class="editing ? '' : 'hover:-translate-y-1'"
                                :aria-label="
                                    editing
                                        ? `Quitar ${item.name} del Inicio`
                                        : undefined
                                "
                                @click="
                                    editing
                                        ? setOnHome(item, false)
                                        : openApp(item)
                                "
                            >
                                <span class="relative">
                                    <AppTile
                                        :icon="item.icon"
                                        :hue="item.hue"
                                        :logo="item.logo"
                                        size="fluid"
                                        :dark="wallDark"
                                    />
                                    <span
                                        v-if="editing"
                                        class="absolute top-1 right-1 grid size-6 place-items-center rounded-full bg-bd-accent-2 text-white shadow-bd-sm"
                                        aria-hidden="true"
                                    >
                                        <i
                                            class="ph-duotone ph-minus text-sm"
                                        />
                                    </span>
                                </span>
                                <span
                                    class="max-w-full text-center text-xs leading-[1.2] break-words sm:text-sm"
                                    >{{ item.name }}</span
                                >
                            </button>
                        </template>
                    </div>
                </div>

                <p
                    v-if="query && !results.length"
                    class="py-6 text-center text-lg text-wall-muted italic"
                >
                    Nada por aquí con «{{ query }}». ¿Buscamos en
                    <Link :href="tools({ query: { q: query } })" class="bd-link"
                        >Herramientas</Link
                    >?
                </p>
                <p
                    v-else-if="!results.length"
                    class="py-6 text-center text-lg text-wall-muted italic"
                >
                    Tu Inicio está vacío.
                    <Link :href="tools()" class="bd-link"
                        >Elige tus apps en Herramientas</Link
                    >.
                </p>
            </div>

            <div class="flex h-2.5 items-center gap-2.5">
                <template v-if="pages.length > 1">
                    <button
                        v-for="(_, i) in pages"
                        :key="i"
                        type="button"
                        :aria-label="`Página ${i + 1}`"
                        :aria-current="i === activePage ? 'page' : undefined"
                        class="h-2 rounded transition-all duration-[250ms]"
                        :class="
                            i === activePage
                                ? 'w-[22px] bg-bd-accent'
                                : 'w-2 bg-current opacity-30'
                        "
                        @click="current = i"
                    />
                </template>
            </div>
        </div>

        <div
            class="flex flex-none flex-wrap items-center justify-between gap-x-5 gap-y-3 px-4 pt-3 pb-5 sm:px-8"
        >
            <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                <span
                    v-if="recentApps.length"
                    class="mr-1 text-[11px] tracking-[0.1em] text-wall-muted uppercase"
                    >Recientes</span
                >
                <button
                    v-for="app in recentApps"
                    :key="app.id"
                    type="button"
                    class="flex items-center gap-2 rounded border border-bd-divider bg-[color-mix(in_srgb,currentColor_5%,transparent)] py-1 pr-3 pl-1 text-[13px] hover:bg-[color-mix(in_srgb,currentColor_11%,transparent)]"
                    @click="openApp(app)"
                >
                    <AppTile
                        :icon="app.icon"
                        :hue="app.hue"
                        :logo="app.logo"
                        :size="28"
                        :dark="wallDark"
                    />
                    {{ app.name }}
                </button>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded border border-bd-divider px-2.5 py-1 text-[13px]"
                    :class="
                        editing
                            ? 'border-bd-accent bg-bd-accent text-bd-bg'
                            : 'hover:bg-[color-mix(in_srgb,currentColor_11%,transparent)]'
                    "
                    :aria-pressed="editing"
                    data-test="home-edit"
                    @click="toggleEditing"
                >
                    <i
                        class="ph-duotone"
                        :class="editing ? 'ph-check' : 'ph-pencil-simple'"
                    />
                    {{ editing ? 'Listo' : 'Editar' }}
                </button>
            </div>
        </div>
    </div>

    <AppLaunchModal :apps="apps" />
</template>
