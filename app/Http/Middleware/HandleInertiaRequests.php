<?php

namespace App\Http\Middleware;

use App\Services\Auth\GoogleLogin;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => (bool) $request->user()?->is_admin,
                // Las cuentas creadas con Google no tienen contraseña.
                'hasPassword' => filled($request->user()?->getAuthPassword()),
            ],
            // Sin credenciales en el .env el botón de Google no se dibuja.
            'googleLoginEnabled' => GoogleLogin::enabled(),
            // Slugs de las apps abiertas hace poco (solo con sesión; los invitados usan localStorage).
            'recents' => fn () => $request->user()?->recentApps()->limit(4)->pluck('slug')->all(),
            'wallpaper' => fn () => $request->user()?->preferences['wallpaper'] ?? null,
            'genericIcons' => fn () => $request->user()?->preferences['generic_icons'] ?? null,
            // Cómo abren las apps (null: sin sesión o sin elegir, vale el de por defecto).
            'appOpen' => fn () => $request->user()?->preferences['app_open'] ?? null,
            // Slugs de las apps que la persona sacó de su Inicio (null sin sesión).
            'homeHidden' => fn () => $request->user() ? ($request->user()->preferences['home_hidden'] ?? []) : null,
        ];
    }
}
