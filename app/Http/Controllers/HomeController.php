<?php

namespace App\Http\Controllers;

use App\Http\Resources\MicroAppResource;
use App\Models\MicroApp;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'apps' => MicroAppResource::collection(
                MicroApp::visible()->ordered()->with('category')->get(),
            )->resolve(),
        ]);
    }
}
