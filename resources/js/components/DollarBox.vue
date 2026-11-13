<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { computed, ref, useId } from 'vue';
import type { Dollar } from '@/types';

const props = defineProps<{
    dollar: Dollar;
    // De qué lado del disparador cae el panel, según dónde esté en la pantalla.
    align: 'left' | 'right';
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const panelId = useId();
// Alto máximo del panel: lo que queda de pantalla debajo del botón.
const maxHeight = ref<string | undefined>();

onClickOutside(root, () => (open.value = false));

const toggle = () => {
    open.value = !open.value;

    if (open.value && root.value) {
        const top = root.value.getBoundingClientRect().bottom + 6;
        maxHeight.value = `${Math.max(160, window.innerHeight - top - 12)}px`;
    }
};

const whole = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});
const cents = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

// "$ 1.570" si es redondo; "$ 1.667,16" si tiene centavos (nunca "$ 1.580,9").
const money = (value: number) =>
    Number.isInteger(value) ? whole.format(value) : cents.format(value);

const amount = (value: number | null) => (value === null ? '—' : money(value));

// El resumen del botón: la venta del oficial y del blue, sin centavos.
const summary = computed(() =>
    props.dollar.rates
        .filter(
            (r) => (r.id === 'oficial' || r.id === 'blue') && r.sell !== null,
        )
        .map((r) => `${r.name} ${whole.format(r.sell as number)}`)
        .join(' · '),
);
</script>

<template>
    <div
        ref="root"
        class="relative max-w-full min-w-0 text-[13px]"
        data-test="dollar-box"
        @keydown.escape="open = false"
    >
        <button
            type="button"
            class="flex max-w-full min-w-0 items-center gap-2 rounded border border-bd-divider bg-[color-mix(in_srgb,currentColor_5%,transparent)] px-2.5 py-1.5 hover:bg-[color-mix(in_srgb,currentColor_11%,transparent)]"
            :aria-expanded="open"
            :aria-controls="panelId"
            data-test="dollar-toggle"
            @click="toggle"
        >
            <i
                class="ph-duotone ph-currency-dollar flex-none text-lg text-bd-accent"
                aria-hidden="true"
            />
            <span class="flex-none whitespace-nowrap">Valor del dólar</span>
            <span
                v-if="summary"
                class="min-w-0 truncate text-wall-muted tabular-nums"
                >{{ summary }}</span
            >
            <i
                class="ph-duotone ph-caret-down flex-none transition-transform duration-[180ms]"
                :class="open ? 'rotate-180' : ''"
                aria-hidden="true"
            />
        </button>

        <div
            v-show="open"
            :id="panelId"
            class="absolute top-full z-20 mt-1.5 w-[340px] max-w-[calc(100vw-2rem)] overflow-y-auto overscroll-contain rounded border border-bd-divider bg-bd-surface p-3 text-bd-text shadow-bd-sm"
            :class="align === 'left' ? 'left-0' : 'right-0'"
            :style="{ maxHeight }"
        >
            <table class="w-full border-collapse">
                <thead>
                    <tr
                        class="text-[11px] tracking-[0.1em] text-bd-muted uppercase"
                    >
                        <th class="pb-1.5 text-left font-normal">Tipo</th>
                        <th class="pb-1.5 text-right font-normal">Compra</th>
                        <th class="pb-1.5 text-right font-normal">Venta</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="rate in dollar.rates"
                        :key="rate.id"
                        class="border-t border-bd-divider align-top"
                    >
                        <td class="py-1.5 pr-2">
                            {{ rate.name }}
                            <span class="block text-xs text-bd-muted">
                                {{ rate.source }} · {{ rate.updatedAt }}
                            </span>
                        </td>
                        <td class="py-1.5 text-right tabular-nums">
                            {{ amount(rate.buy) }}
                        </td>
                        <td class="py-1.5 pl-2 text-right tabular-nums">
                            {{ amount(rate.sell) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p
                class="mt-2 border-t border-bd-divider pt-2 text-xs text-bd-muted"
            >
                Valores informativos. Fuentes: Banco Nación, BCRA y
                <a
                    href="https://dolarapi.com"
                    target="_blank"
                    rel="noopener"
                    class="bd-link"
                    >DolarApi</a
                >.
            </p>
        </div>
    </div>
</template>
