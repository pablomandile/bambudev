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

];
