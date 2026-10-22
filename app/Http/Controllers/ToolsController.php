<?php

namespace App\Http\Controllers;

use App\Http\Resources\MicroAppResource;
use App\Models\MicroApp;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ToolsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Tools', [
            'apps' => MicroAppResource::collection(
                MicroApp::visible()->ordered()->with('category')->get(),
            )->resolve(),
            'initialQuery' => (string) $request->string('q')->limit(100, ''),
        ]);
    }
}
