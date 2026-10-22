import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { open as openRoute } from '@/routes/apps';
import { update as updatePreferences } from '@/routes/preferences';
import type { MicroApp, Wallpaper } from '@/types';

const RECENTS_KEY = 'bambudev.recents';
const WALLPAPER_KEY = 'bambudev.wallpaper';
const MAX_RECENTS = 4;

const readLocal = <T>(key: string, fallback: T): T => {
    try {
        const raw = localStorage.getItem(key);

        return raw ? (JSON.parse(raw) as T) : fallback;
    } catch {
        return fallback;
    }
};

const writeLocal = (key: string, value: unknown) => {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Sin almacenamiento (modo privado): la preferencia dura lo que la pestaña.
    }
};

// Estado compartido entre páginas: los invitados guardan todo en el navegador.
const guestRecents = ref<string[]>([]);
const guestWallpaper = ref<Wallpaper>('bosque');
const openId = ref<string | null>(null);
let hydrated = false;

/**
 * Preferencias del portal (fondo y recientes) y apertura de apps. Con sesión
 * se guardan en el perfil; sin sesión, en localStorage.
 */
export function usePortal() {
    const page = usePage();

    if (!hydrated && typeof window !== 'undefined') {
        hydrated = true;
        guestRecents.value = readLocal<string[]>(RECENTS_KEY, []);
        guestWallpaper.value = readLocal<Wallpaper>(WALLPAPER_KEY, 'bosque');
    }

    const isGuest = computed(() => !page.props.auth.user);

    const recents = computed<string[]>(() =>
        isGuest.value ? guestRecents.value : (page.props.recents ?? []),
    );

    const wallpaper = computed<Wallpaper>(() =>
        isGuest.value
            ? guestWallpaper.value
            : (page.props.wallpaper ?? 'bosque'),
    );

    function setWallpaper(value: Wallpaper) {
        if (isGuest.value) {
            guestWallpaper.value = value;
            writeLocal(WALLPAPER_KEY, value);

            return;
        }

        router.patch(
            updatePreferences().url,
            { wallpaper: value },
            { preserveScroll: true, preserveState: true, only: ['wallpaper'] },
        );
    }

    function rememberGuestRecent(id: string) {
        guestRecents.value = [
            id,
            ...guestRecents.value.filter((r) => r !== id),
        ].slice(0, MAX_RECENTS);
        writeLocal(RECENTS_KEY, guestRecents.value);
    }

    /** Muestra la tarjeta de confirmación de la app. */
    function showApp(app: MicroApp) {
        openId.value = app.id;
    }

    function closeApp() {
        openId.value = null;
    }

    /** Lleva al usuario al destino de la app (y la anota en recientes). */
    function launch(app: MicroApp) {
        if (isGuest.value) {
            rememberGuestRecent(app.id);
        }

        closeApp();
        router.post(openRoute(app.id).url, {}, { preserveScroll: true });
    }

    return {
        isGuest,
        recents,
        wallpaper,
        setWallpaper,
        openId,
        showApp,
        closeApp,
        launch,
    };
}
