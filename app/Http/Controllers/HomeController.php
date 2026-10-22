<?php

namespace App\Http\Controllers;

use App\Http\Resources\MicroAppResource;
use App\Models\MicroApp;
use App\Services\Weather\CabaWeather;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(CabaWeather $weather): Response
    {
        return Inertia::render('Home', [
            'apps' => MicroAppResource::collection(
                MicroApp::visible()->ordered()->with('category')->get(),
            )->resolve(),
            // Diferida: el Inicio no espera a Open-Meteo, y si algo falla queda vacía.
            'weather' => Inertia::defer(fn () => $weather->current(), rescue: true),
        ]);
    }
}
