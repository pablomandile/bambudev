import type { CSSProperties } from 'vue';
import type { MicroApp, Wallpaper } from '@/types';

/** Normaliza para buscar sin distinguir acentos ni mayúsculas. */
export const normalize = (s: string): string =>
    s
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

export function matchesQuery(app: MicroApp, query: string): boolean {
    const q = normalize(query.trim());

    if (!q) {
        return true;
    }

    return normalize(`${app.name} ${app.cat} ${app.tags} ${app.desc}`).includes(
        q,
    );
}

/**
 * Estilo del cuadradito de color de una app. Sobre fondos oscuros (tema
 * oscuro o el fondo Bosque) usa la variante oscura.
 */
export function tileStyle(
    hue: number,
    size: number | 'fluid',
    dark: boolean,
): CSSProperties {
    const fluid = size === 'fluid';

    return {
        width: fluid ? 'clamp(54px,10vh,84px)' : `${size}px`,
        height: fluid ? 'clamp(54px,10vh,84px)' : `${size}px`,
        flex: 'none',
        display: 'grid',
        placeItems: 'center',
        borderRadius: '4px',
        fontSize: fluid
            ? 'clamp(27px,5vh,42px)'
            : `${Math.round(size * 0.5)}px`,
        background: dark
            ? `oklch(0.34 0.07 ${hue})`
            : `oklch(0.92 0.055 ${hue})`,
        color: dark ? `oklch(0.86 0.11 ${hue})` : `oklch(0.44 0.13 ${hue})`,
    };
}

export const WALLPAPERS: { id: Wallpaper; label: string; dark?: boolean }[] = [
    { id: 'papel', label: 'Papel' },
    { id: 'bambu', label: 'Bambú' },
    { id: 'trama', label: 'Trama' },
    { id: 'bosque', label: 'Bosque', dark: true },
];

/** Iconos Phosphor que ofrece el panel de alta de apps. */
export const ICON_OPTIONS = [
    'ph-lightning',
    'ph-shopping-cart',
    'ph-airplane-tilt',
    'ph-cooking-pot',
    'ph-barbell',
    'ph-plant',
    'ph-music-notes',
    'ph-book-open',
    'ph-car',
    'ph-receipt',
    'ph-graduation-cap',
    'ph-baby',
    'ph-timer',
    'ph-chat-circle',
    'ph-camera',
    'ph-gift',
];

export const HUE_OPTIONS = [140, 20, 60, 85, 330, 275, 235, 195];
