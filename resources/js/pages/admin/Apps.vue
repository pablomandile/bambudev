<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppTile from '@/components/AppTile.vue';
import InputError from '@/components/InputError.vue';
import { useAppearance } from '@/composables/useAppearance';
import { HUE_OPTIONS, ICON_OPTIONS } from '@/lib/portal';
import { destroy, move, store, update, visibility } from '@/routes/admin/apps';
import type { Category, MicroApp } from '@/types';

const props = defineProps<{ apps: MicroApp[]; categories: Category[] }>();

const { resolvedAppearance } = useAppearance();
const dark = computed(() => resolvedAppearance.value === 'dark');

// — Tabla —
const keep = { preserveScroll: true, preserveState: true };

const moveApp = (app: MicroApp, direction: 'up' | 'down') =>
    router.patch(move(app.id).url, { direction }, keep);

const toggleVisible = (app: MicroApp) =>
    router.patch(visibility(app.id).url, {}, keep);

// Eliminar pide un segundo click sobre el mismo botón.
const pendingDelete = ref<string | null>(null);
let pendingTimer: ReturnType<typeof setTimeout> | undefined;

const removeApp = (app: MicroApp) => {
    if (pendingDelete.value !== app.id) {
        pendingDelete.value = app.id;
        clearTimeout(pendingTimer);
        pendingTimer = setTimeout(() => (pendingDelete.value = null), 3000);

        return;
    }

    pendingDelete.value = null;

    if (editing.value === app.id) {
        resetDraft();
    }

    router.delete(destroy(app.id).url, keep);
};

// — Panel de alta / edición —
const defaultCategory = () =>
    props.categories.find((c) => c.name === 'Organización')?.id ??
    props.categories[0]?.id ??
    0;

const editing = ref<string | null>(null);

const form = useForm({
    name: '',
    description: '',
    category_id: defaultCategory(),
    icon: 'ph-lightning',
    hue: 140,
    tags: '',
    target_type: 'external' as MicroApp['targetType'],
    target: '',
});

const draftName = computed(() => form.name.trim() || 'Tu nueva app');
const draftCategory = computed(
    () => props.categories.find((c) => c.id === form.category_id)?.name ?? '',
);

function resetDraft() {
    editing.value = null;
    const category = form.category_id;
    form.reset();
    form.clearErrors();
    form.category_id = category;
}

function editApp(app: MicroApp) {
    editing.value = app.id;
    form.clearErrors();
    form.name = app.name;
    form.description = app.desc;
    form.category_id = app.categoryId;
    form.icon = app.icon;
    form.hue = app.hue;
    form.tags = app.tags;
    form.target_type = app.targetType;
    form.target = app.target ?? '';

    // En una columna el panel queda debajo de la lista: lo traemos a la vista.
    if (window.matchMedia('(max-width: 1023px)').matches) {
        formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

const formEl = ref<HTMLFormElement | null>(null);

const iconChoices = computed(() =>
    ICON_OPTIONS.includes(form.icon)
        ? ICON_OPTIONS
        : [form.icon, ...ICON_OPTIONS],
);
const hueChoices = computed(() =>
    HUE_OPTIONS.includes(form.hue) ? HUE_OPTIONS : [form.hue, ...HUE_OPTIONS],
);

const submit = () => {
    if (editing.value) {
        form.put(update(editing.value).url, {
            ...keep,
            onSuccess: () => resetDraft(),
        });

        return;
    }

    form.post(store().url, { ...keep, onSuccess: () => resetDraft() });
};
</script>

<template>
    <Head title="Admin · Tus apps" />

    <div
        class="mx-auto flex w-full max-w-[1240px] flex-col gap-9 px-4 pt-10 pb-28 sm:px-10 sm:pt-12"
    >
        <div>
            <div class="mb-3 flex items-center gap-2">
                <span class="tag tag-accent-2">Área administrativa</span>
                <span class="text-[13px] whitespace-nowrap text-bd-muted"
                    >Solo tú ves esto</span
                >
            </div>
            <h1 class="mb-2 text-[40px] tracking-[-0.03em] sm:text-5xl">
                Tus apps
            </h1>
            <p class="m-0 text-[17px] text-bd-muted">
                Lo que publiques aquí aparece en el Inicio al instante. El orden
                de la lista es el orden de los iconos.
            </p>
        </div>

        <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_380px]">
            <table class="table max-md:hidden">
                <thead>
                    <tr>
                        <th class="w-14">Orden</th>
                        <th>App</th>
                        <th class="max-md:hidden">Categoría</th>
                        <th>En Inicio</th>
                        <th class="w-[84px]">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(app, i) in apps"
                        :key="app.id"
                        :class="{ 'bg-bd-hover': editing === app.id }"
                    >
                        <td>
                            <div class="flex flex-col">
                                <button
                                    type="button"
                                    class="btn btn-ghost px-1 py-0 text-bd-muted"
                                    :disabled="i === 0"
                                    :aria-label="`Subir ${app.name}`"
                                    @click="moveApp(app, 'up')"
                                >
                                    <i class="ph-duotone ph-caret-up" />
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-ghost px-1 py-0 text-bd-muted"
                                    :disabled="i === apps.length - 1"
                                    :aria-label="`Bajar ${app.name}`"
                                    @click="moveApp(app, 'down')"
                                >
                                    <i class="ph-duotone ph-caret-down" />
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="flex items-center gap-3">
                                <AppTile
                                    :icon="app.icon"
                                    :hue="app.hue"
                                    :size="40"
                                    :dark="dark"
                                />
                                <div class="min-w-0">
                                    <div class="text-[15px] font-semibold">
                                        {{ app.name }}
                                        <i
                                            v-if="!app.hasTarget"
                                            class="ph-duotone ph-link-break align-middle text-bd-muted"
                                            title="Sin destino: todavía no se puede abrir"
                                        />
                                    </div>
                                    <div
                                        class="max-w-[340px] truncate text-xs text-bd-muted"
                                    >
                                        {{ app.desc }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="max-md:hidden">
                            <span class="tag tag-neutral">{{ app.cat }}</span>
                        </td>
                        <td>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="app.visible"
                                :aria-label="`Mostrar ${app.name} en el Inicio`"
                                class="flex h-[22px] w-10 rounded-[11px] p-0.5 transition-colors duration-200"
                                :class="
                                    app.visible
                                        ? 'justify-end bg-bd-accent'
                                        : 'justify-start bg-bd-divider'
                                "
                                @click="toggleVisible(app)"
                            >
                                <span
                                    class="block size-[18px] rounded-full bg-white shadow-[0_1px_2px_rgba(0,0,0,.3)]"
                                />
                            </button>
                        </td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    title="Editar"
                                    :aria-label="`Editar ${app.name}`"
                                    class="btn btn-secondary btn-icon size-8"
                                    @click="editApp(app)"
                                >
                                    <i class="ph-duotone ph-pencil-simple" />
                                </button>
                                <button
                                    type="button"
                                    :title="
                                        pendingDelete === app.id
                                            ? 'Click otra vez para eliminar'
                                            : 'Eliminar'
                                    "
                                    :aria-label="`Eliminar ${app.name}`"
                                    class="btn btn-secondary btn-icon size-8 text-bd-accent-2-700"
                                    :class="{
                                        'bg-bd-accent-2! text-white!':
                                            pendingDelete === app.id,
                                    }"
                                    @click="removeApp(app)"
                                >
                                    <i class="ph-duotone ph-trash" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Mobile/tablet: una tarjeta por app -->
            <div class="flex min-w-0 flex-col gap-3 md:hidden">
                <article
                    v-for="(app, i) in apps"
                    :key="app.id"
                    class="flex flex-col gap-3 rounded border border-bd-divider p-4"
                    :class="{ 'bg-bd-hover': editing === app.id }"
                >
                    <div class="flex items-start gap-3">
                        <AppTile
                            :icon="app.icon"
                            :hue="app.hue"
                            :size="40"
                            :dark="dark"
                        />
                        <div class="min-w-0 flex-1">
                            <div
                                class="text-[15px] font-semibold wrap-anywhere"
                            >
                                {{ app.name }}
                                <i
                                    v-if="!app.hasTarget"
                                    class="ph-duotone ph-link-break align-middle text-bd-muted"
                                    title="Sin destino: todavía no se puede abrir"
                                />
                            </div>
                            <span class="tag tag-neutral mt-1">{{
                                app.cat
                            }}</span>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="app.visible"
                            :aria-label="`Mostrar ${app.name} en el Inicio`"
                            class="flex h-[22px] w-10 flex-none rounded-[11px] p-0.5 transition-colors duration-200"
                            :class="
                                app.visible
                                    ? 'justify-end bg-bd-accent'
                                    : 'justify-start bg-bd-divider'
                            "
                            @click="toggleVisible(app)"
                        >
                            <span
                                class="block size-[18px] rounded-full bg-white shadow-[0_1px_2px_rgba(0,0,0,.3)]"
                            />
                        </button>
                    </div>
                    <p
                        v-if="app.desc"
                        class="m-0 text-[13px] text-pretty text-bd-muted"
                    >
                        {{ app.desc }}
                    </p>
                    <div
                        class="flex items-center gap-2 border-t border-bd-divider pt-3"
                    >
                        <button
                            type="button"
                            class="btn btn-secondary btn-icon size-10"
                            :disabled="i === 0"
                            :aria-label="`Subir ${app.name}`"
                            @click="moveApp(app, 'up')"
                        >
                            <i class="ph-duotone ph-caret-up" />
                        </button>
                        <button
                            type="button"
                            class="btn btn-secondary btn-icon size-10"
                            :disabled="i === apps.length - 1"
                            :aria-label="`Bajar ${app.name}`"
                            @click="moveApp(app, 'down')"
                        >
                            <i class="ph-duotone ph-caret-down" />
                        </button>
                        <button
                            type="button"
                            class="btn btn-secondary ml-auto h-10"
                            @click="editApp(app)"
                        >
                            <i class="ph-duotone ph-pencil-simple" />
                            Editar
                        </button>
                        <button
                            type="button"
                            :aria-label="`Eliminar ${app.name}`"
                            class="btn btn-secondary h-10 text-bd-accent-2-700"
                            :class="{
                                'bg-bd-accent-2! text-white!':
                                    pendingDelete === app.id,
                            }"
                            @click="removeApp(app)"
                        >
                            <i class="ph-duotone ph-trash" />
                            {{ pendingDelete === app.id ? '¿Seguro?' : '' }}
                        </button>
                    </div>
                </article>
            </div>

            <form
                ref="formEl"
                class="flex min-w-0 flex-col gap-4 rounded bg-bd-surface p-[26px] lg:sticky lg:top-6"
                @submit.prevent="submit"
            >
                <h3 class="m-0 text-[25px]">
                    {{ editing ? 'Editar app' : 'Nueva app' }}
                </h3>

                <div class="flex items-center gap-3.5 rounded bg-bd-bg p-3.5">
                    <AppTile
                        :icon="form.icon"
                        :hue="form.hue"
                        :size="56"
                        :dark="dark"
                    />
                    <div class="min-w-0">
                        <div class="truncate text-[17px] font-semibold">
                            {{ draftName }}
                        </div>
                        <div class="text-xs text-bd-muted">
                            {{ draftCategory }} · vista previa
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="app-name">Nombre</label>
                    <input
                        id="app-name"
                        v-model="form.name"
                        class="input"
                        placeholder="ej. ListaSúper"
                        maxlength="40"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="field">
                    <label for="app-desc">Descripción corta</label>
                    <textarea
                        id="app-desc"
                        v-model="form.description"
                        class="input min-h-[70px]"
                        placeholder="¿Qué problema le resuelve a alguien un martes cualquiera?"
                        maxlength="500"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="field">
                    <label for="app-cat">Categoría</label>
                    <select
                        id="app-cat"
                        v-model.number="form.category_id"
                        class="input"
                    >
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.category_id" />
                </div>
                <div class="field">
                    <label>Icono</label>
                    <div class="grid grid-cols-8 gap-1">
                        <button
                            v-for="icon in iconChoices"
                            :key="icon"
                            type="button"
                            :aria-label="icon.replace('ph-', '')"
                            :aria-pressed="form.icon === icon"
                            class="grid aspect-square place-items-center rounded-[2px] text-xl"
                            :class="
                                form.icon === icon
                                    ? 'bg-bd-accent text-bd-bg'
                                    : 'text-bd-text hover:bg-bd-hover'
                            "
                            @click="form.icon = icon"
                        >
                            <i class="ph-duotone" :class="icon" />
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label>Color</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="hue in hueChoices"
                            :key="hue"
                            type="button"
                            :aria-label="`Tono ${hue}`"
                            :aria-pressed="form.hue === hue"
                            class="size-[26px] rounded-[2px]"
                            :style="{
                                background: `oklch(0.7 0.13 ${hue})`,
                                boxShadow:
                                    form.hue === hue
                                        ? '0 0 0 2px var(--bd-surface), 0 0 0 4px var(--bd-text)'
                                        : 'none',
                            }"
                            @click="form.hue = hue"
                        />
                    </div>
                </div>

                <div class="field">
                    <label>Destino</label>
                    <div
                        class="mb-2 inline-flex overflow-hidden rounded-[2px] border border-bd-divider text-[13px]"
                    >
                        <button
                            v-for="opt in [
                                { id: 'external', label: 'Otro sitio' },
                                { id: 'internal', label: 'En bambudev' },
                            ] as const"
                            :key="opt.id"
                            type="button"
                            class="px-3 py-[7px] not-first:border-l not-first:border-bd-divider"
                            :class="
                                form.target_type === opt.id
                                    ? 'bg-bd-accent text-bd-bg'
                                    : 'hover:bg-bd-hover'
                            "
                            :aria-pressed="form.target_type === opt.id"
                            @click="form.target_type = opt.id"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <input
                        v-model="form.target"
                        class="input"
                        :aria-label="
                            form.target_type === 'external'
                                ? 'URL de la app'
                                : 'Ruta de la app'
                        "
                        :placeholder="
                            form.target_type === 'external'
                                ? 'https://agendaflex.com.ar'
                                : '/apps/billetera'
                        "
                    />
                    <InputError :message="form.errors.target" />
                    <p class="mt-1 mb-0 text-xs text-bd-muted">
                        Si lo dejas vacío, la app aparece pero todavía no se
                        puede abrir.
                    </p>
                </div>
                <div class="field">
                    <label for="app-tags">Palabras para la búsqueda</label>
                    <input
                        id="app-tags"
                        v-model="form.tags"
                        class="input"
                        placeholder="ej. compras super lista"
                    />
                    <InputError :message="form.errors.tags" />
                </div>

                <button
                    type="submit"
                    class="btn btn-primary btn-block"
                    :disabled="!form.name.trim() || form.processing"
                >
                    {{ editing ? 'Guardar cambios' : 'Publicar en el Inicio' }}
                </button>
                <button
                    v-if="editing"
                    type="button"
                    class="btn btn-secondary"
                    @click="resetDraft"
                >
                    Cancelar
                </button>
            </form>
        </div>
    </div>
</template>
