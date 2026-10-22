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

];
