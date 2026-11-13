<?php

use App\Http\Controllers\Api\DollarController;
use Illuminate\Support\Facades\Route;

/*
| La API propia, para las apps de bambudev (MiBilletera…). Todo pide la clave
| de la app en el header X-Api-Key (ver RequireApiKey) y tiene un tope por app.
*/
Route::prefix('v1')->middleware(['api.key', 'throttle:api'])->name('api.')->group(function () {
    Route::get('dolares', [DollarController::class, 'index'])->name('dollar.index');
    Route::get('dolares/{tipo}', [DollarController::class, 'show'])->name('dollar.show');
    // Antes que {fecha}, aunque el patrón de la fecha ya los separa.
    Route::get('dolares/{tipo}/historico', [DollarController::class, 'history'])->name('dollar.history');
    Route::get('dolares/{tipo}/{fecha}', [DollarController::class, 'onDate'])
        ->where('fecha', '\d{4}-\d{2}-\d{2}')
        ->name('dollar.date');
});
