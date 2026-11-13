<?php

namespace App\Http\Controllers;

use App\Http\Resources\MicroAppResource;
use App\Models\MicroApp;
use App\Services\Dollar\DollarRates;
use App\Services\Weather\CabaWeather;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, CabaWeather $weather, DollarRates $dollar): Response
    {
        return Inertia::render('Home', [
            'apps' => MicroAppResource::collection(
                MicroApp::availableTo($request->user())->ordered()->with('category')->get(),
            )->resolve(),
            // Diferida: el Inicio no espera a Open-Meteo, y si algo falla queda vacía.
            'weather' => Inertia::defer(fn () => $weather->current(), rescue: true),
            // En su propio grupo: una página del BNA lenta no demora al tiempo.
            'dollar' => Inertia::defer(fn () => $dollar->current(), group: 'dollar', rescue: true),
        ]);
    }
}
