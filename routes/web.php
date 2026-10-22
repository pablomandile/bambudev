<?php

use App\Http\Controllers\Admin\MicroAppController;
use App\Http\Controllers\AppLaunchController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ToolsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('herramientas', ToolsController::class)->name('tools');
Route::inertia('acerca', 'About')->name('about');
Route::get('ayuda', [ContactController::class, 'show'])->name('help');
Route::post('ayuda', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('help.store');

Route::post('apps/{microApp}/abrir', AppLaunchController::class)->name('apps.open');

// Ingreso con Google. `guest`: con la sesión abierta, rehacer el flujo solo
// cambiaría de cuenta sin cerrar la anterior.
Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function () {
    Route::patch('preferencias', [PreferenceController::class, 'update'])->name('preferences.update');
    Route::patch('inicio/{microApp}', [PreferenceController::class, 'home'])->name('preferences.home');
});

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/apps');
    Route::get('apps', [MicroAppController::class, 'index'])->name('apps.index');
    Route::post('apps', [MicroAppController::class, 'store'])->name('apps.store');
    Route::put('apps/{microApp}', [MicroAppController::class, 'update'])->name('apps.update');
    Route::delete('apps/{microApp}', [MicroAppController::class, 'destroy'])->name('apps.destroy');
    Route::patch('apps/{microApp}/visibilidad', [MicroAppController::class, 'visibility'])->name('apps.visibility');
    Route::patch('apps/{microApp}/mover', [MicroAppController::class, 'move'])->name('apps.move');
});

require __DIR__.'/settings.php';
