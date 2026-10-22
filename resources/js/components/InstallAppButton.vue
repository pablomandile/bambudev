<script setup lang="ts">
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePwaInstall } from '@/composables/usePwaInstall';

const { canInstall, isIos, install } = usePwaInstall();
const showHelp = ref(false);
const busy = ref(false);

const onClick = async () => {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        // 'manual' = iOS, o el prompt ya se usó: se explican los pasos a mano.
        if ((await install()) === 'manual') {
            showHelp.value = true;
        }
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <button
        v-if="canInstall"
        type="button"
        class="btn btn-secondary btn-icon"
        title="Instalar app"
        aria-label="Instalar app"
        data-test="install-app-button"
        :disabled="busy"
        @click="onClick"
    >
        <i class="ph-duotone ph-download-simple text-[19px]" />
    </button>

    <Dialog v-model:open="showHelp">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Instalar bambudev</DialogTitle>
                <DialogDescription>
                    Así la tienes a mano como una app más, sin abrir el
                    navegador.
                </DialogDescription>
            </DialogHeader>
            <ol class="list-decimal space-y-2 pl-5 text-sm">
                <template v-if="isIos">
                    <li>
                        Toca <strong>Compartir</strong> en la barra de Safari.
                    </li>
                    <li>Elige <strong>Agregar a inicio</strong>.</li>
                </template>
                <template v-else>
                    <li>Abre el menú del navegador (los tres puntos).</li>
                    <li>
                        Elige <strong>Instalar aplicación</strong> o
                        <strong>Agregar a la pantalla principal</strong>.
                    </li>
                </template>
            </ol>
        </DialogContent>
    </Dialog>
</template>
