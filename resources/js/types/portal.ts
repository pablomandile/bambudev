export type MicroApp = {
    id: string;
    name: string;
    cat: string;
    categoryId: number;
    icon: string;
    hue: number;
    logo: string | null;
    tags: string;
    desc: string;
    visible: boolean;
    // Solo la ven y la abren los admins.
    adminOnly: boolean;
    targetType: 'internal' | 'external';
    target: string | null;
    hasTarget: boolean;
};

// El tiempo en CABA (ver App\Services\Weather\CabaWeather).
export type Weather = {
    temp: number;
    feelsLike: number;
    max: number | null;
    min: number | null;
    code: number;
    isDay: boolean;
    label: string;
    icon: string;
    place: string;
    updatedAt: string;
};

// Una cotización del dólar (ver App\Services\Dollar\DollarRates).
export type DollarRate = {
    id: string;
    name: string;
    buy: number | null;
    sell: number | null;
    source: string;
    updatedAt: string;
};

export type Dollar = {
    rates: DollarRate[];
    updatedAt: string;
};

export type Category = {
    id: number;
    name: string;
};

// Cómo abren las apps (ver PreferenceController::APP_OPEN_MODES).
export type AppOpenMode = 'new_tab' | 'ask';

export type Wallpaper =
    | 'papel'
    | 'bambu'
    | 'trama'
    | 'bosque'
    | 'esterilla'
    | 'canizo';
