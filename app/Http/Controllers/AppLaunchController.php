<?php

namespace App\Http\Controllers;

use App\Models\MicroApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AppLaunchController extends Controller
{
    /**
     * Abre una app: la anota en los recientes del usuario y lo lleva a su destino.
     */
    public function __invoke(Request $request, MicroApp $microApp): Response|RedirectResponse
    {
        abort_unless($microApp->visible, 404);

        $request->user()?->touchRecentApp($microApp);

        $url = $microApp->launchUrl();

        if ($url === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "«{$microApp->name}» todavía está creciendo. Muy pronto vas a poder abrirla."]);

            return back();
        }

        return $microApp->target_type === MicroApp::TARGET_INTERNAL
            ? redirect()->to($url)
            : Inertia::location($url);
    }
}
