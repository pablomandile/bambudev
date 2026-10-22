<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/help';

defineProps<{ appOptions: { id: string; name: string }[] }>();

const page = usePage();

const faqs = [
    [
        '¿Necesito una cuenta para cada app?',
        'No. Con tu cuenta bambudev entras a todas. Una llave, muchas puertas.',
    ],
    [
        '¿Mis datos se comparten entre apps?',
        'Solo si tú lo permites. Cada app guarda lo suyo y te pide permiso antes de cruzar información.',
    ],
    [
        '¿Cómo cambio el fondo del Inicio?',
        'Abajo en el Inicio tienes los cuadraditos de «Fondo». Toca uno y listo, redecoraste.',
    ],
    [
        'Tengo más apps de las que entran en pantalla',
        'El Inicio se pagina solo: desliza, usa las flechas del teclado o toca los puntitos de abajo.',
    ],
    [
        '¿Cómo sugiero una app nueva?',
        'Escríbenos con el formulario de al lado. Las mejores ideas suelen empezar con «sería genial si…».',
    ],
];

const shortcuts = [
    ['/', 'Buscar una app en el Inicio'],
    ['← →', 'Cambiar de página de apps'],
    ['Esc', 'Cerrar una ventana'],
];

const openFaq = ref(0);
const toggleFaq = (i: number) => (openFaq.value = openFaq.value === i ? -1 : i);

const form = useForm({
    email: page.props.auth.user?.email ?? '',
    app: '',
    message: '',
});

const submit = () =>
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('message', 'app'),
    });
</script>

<template>
    <Head title="Ayuda" />

    <div
        class="mx-auto grid w-full max-w-[1180px] items-start gap-12 px-4 pt-10 pb-28 sm:px-10 sm:pt-14 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-[72px]"
    >
        <div>
            <div class="kicker mb-2.5">Ayuda</div>
            <h1 class="mb-3 text-[40px] tracking-[-0.03em] sm:text-[56px]">
                ¿Te trabaste?
            </h1>
            <p class="mb-10 text-lg text-bd-muted">
                Respiramos hondo y lo resolvemos. Empieza por aquí.
            </p>

            <div class="flex flex-col border-t border-bd-divider">
                <div
                    v-for="([q, a], i) in faqs"
                    :key="q"
                    class="border-b border-bd-divider"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-5 py-5 text-left text-[19px] leading-[1.3] font-semibold hover:text-bd-accent-700"
                        :aria-expanded="openFaq === i"
                        @click="toggleFaq(i)"
                    >
                        {{ q }}
                        <i
                            class="ph-duotone flex-none text-xl text-bd-accent"
                            :class="openFaq === i ? 'ph-minus' : 'ph-plus'"
                        />
                    </button>
                    <p
                        v-if="openFaq === i"
                        class="m-0 pr-12 pb-[22px] text-base text-pretty text-bd-muted"
                    >
                        {{ a }}
                    </p>
                </div>
            </div>

            <h4 class="mt-12 mb-4 text-xl">Atajos de teclado</h4>
            <div
                class="grid grid-cols-[auto_1fr] items-center gap-x-[18px] gap-y-2.5 text-[15px]"
            >
                <template v-for="[key, label] in shortcuts" :key="key">
                    <kbd
                        class="justify-self-start rounded-[2px] border border-bd-divider px-2.5 py-[3px] font-serif text-[13px] font-semibold"
                        >{{ key }}</kbd
                    >
                    <span>{{ label }}</span>
                </template>
            </div>
        </div>

        <form
            class="flex flex-col gap-4 rounded bg-bd-surface p-7 lg:sticky lg:top-6"
            @submit.prevent="submit"
        >
            <h3 class="m-0 text-[25px]">Escríbenos</h3>
            <div class="field">
                <label for="help-email">Tu email</label>
                <input
                    id="help-email"
                    v-model="form.email"
                    type="email"
                    required
                    class="input"
                    placeholder="tu@correo.com"
                    autocomplete="email"
                />
                <InputError :message="form.errors.email" />
            </div>
            <div class="field">
                <label for="help-app">¿Sobre qué app?</label>
                <select id="help-app" v-model="form.app" class="input">
                    <option value="">General</option>
                    <option v-for="o in appOptions" :key="o.id" :value="o.id">
                        {{ o.name }}
                    </option>
                </select>
                <InputError :message="form.errors.app" />
            </div>
            <div class="field">
                <label for="help-message">Cuéntanos</label>
                <textarea
                    id="help-message"
                    v-model="form.message"
                    required
                    class="input"
                    placeholder="Pasó esto, esperaba aquello…"
                />
                <InputError :message="form.errors.message" />
            </div>
            <button
                type="submit"
                class="btn btn-primary btn-block"
                :disabled="form.processing"
            >
                Enviar mensaje
            </button>
        </form>
    </div>
</template>
