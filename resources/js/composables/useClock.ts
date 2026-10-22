import { computed, onMounted, onUnmounted, ref } from 'vue';

const capitalize = (t: string) => t.charAt(0).toUpperCase() + t.slice(1);

/** Reloj del Inicio: se actualiza cada 15 segundos. */
export function useClock() {
    const now = ref(new Date());
    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        now.value = new Date();
        timer = setInterval(() => (now.value = new Date()), 15000);
    });

    onUnmounted(() => clearInterval(timer));

    const time = computed(() =>
        now.value.toLocaleTimeString('es', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }),
    );

    const date = computed(() =>
        capitalize(
            now.value.toLocaleDateString('es', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
            }),
        ),
    );

    const salutation = computed(() => {
        const h = now.value.getHours();

        return h < 12 ? 'Buen día' : h < 20 ? 'Buenas tardes' : 'Buenas noches';
    });

    return { now, time, date, salutation };
}
