<?php

return [

    /*
    | Cuenta administradora que crea el seeder (DatabaseSeeder). En producción
    | definí ADMIN_EMAIL y ADMIN_PASSWORD en el .env antes de sembrar.
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@bambudev.test'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

    /*
    | El tiempo del Inicio: siempre la Ciudad de Buenos Aires (el Obelisco), sin
    | geolocalización. Open-Meteo no pide API key. `fresh` es cuánto se sirve sin
    | volver a preguntar y `stale` hasta cuándo se sirve el dato viejo mientras se
    | refresca por detrás (segundos).
    */
    'weather' => [
        'url' => 'https://api.open-meteo.com/v1/forecast',
        'latitude' => -34.6037,
        'longitude' => -58.3816,
        'timezone' => 'America/Argentina/Buenos_Aires',
        'label' => 'CABA',
        'fresh' => 900,
        'stale' => 7200,
    ],

    /*
    | El valor del dólar del Inicio, combinando fuentes: el oficial minorista de
    | Banco Nación (no tiene API: se lee su página), el mayorista del BCRA (API
    | pública, sin token) y el resto de DolarApi (blue, MEP, CCL, cripto y el blue
    | de Ámbito). Si BNA o el BCRA fallan, ese valor sale de DolarApi.
    | `card_factor` es el recargo del dólar tarjeta sobre el oficial (percepción
    | del 30 % desde ene-2026): si cambia la normativa, se ajusta acá.
    */
    'dollar' => [
        'dolarapi' => 'https://dolarapi.com/v1',
        'bcra' => 'https://api.bcra.gob.ar/estadisticascambiarias/v1.0/Cotizaciones',
        'bna' => 'https://www.bna.com.ar/Personas',
        'user_agent' => 'bambudev-portal (+https://bambudev.com.ar)',
        'card_factor' => 1.30,
        'timezone' => 'America/Argentina/Buenos_Aires',
        'fresh' => 900,
        'stale' => 7200,
    ],

    /*
    | Las claves de la API propia (/api/v1), una por app que la usa. En el .env:
    | API_KEYS="mibilletera:<clave>,otra:<clave>". Quedan como [clave => app]. Sin
    | claves, la API rechaza todo.
    */
    'api' => [
        'keys' => collect(explode(',', (string) env('API_KEYS', '')))
            ->map(fn (string $pair) => array_map('trim', explode(':', $pair, 2)))
            ->filter(fn (array $pair) => count($pair) === 2 && $pair[0] !== '' && strlen($pair[1]) >= 20)
            ->mapWithKeys(fn (array $pair) => [$pair[1] => $pair[0]])
            ->all(),
        'per_minute' => 60,
    ],

];
