export type MicroApp = {
    id: string;
    name: string;
    cat: string;
    categoryId: number;
    icon: string;
    hue: number;
    tags: string;
    desc: string;
    visible: boolean;
    targetType: 'internal' | 'external';
    target: string | null;
    hasTarget: boolean;
};

export type Category = {
    id: number;
    name: string;
};

export type Wallpaper = 'papel' | 'bambu' | 'trama' | 'bosque';
