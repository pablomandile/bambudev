<?php

namespace App\Http\Controllers;

use App\Models\MicroApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public const WALLPAPERS = ['papel', 'bambu', 'trama', 'bosque'];

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wallpaper' => ['sometimes', 'required', Rule::in(self::WALLPAPERS)],
            'generic_icons' => ['sometimes', 'required', 'boolean'],
        ]);

        if (array_key_exists('generic_icons', $validated)) {
            $validated['generic_icons'] = $request->boolean('generic_icons');
        }

        $user = $request->user();
        $user->preferences = [...($user->preferences ?? []), ...$validated];
        $user->save();

        return back();
    }

    /**
     * Pone o saca una app del Inicio de la persona. Se guardan las que sacó,
     * así las apps nuevas del catálogo aparecen en el Inicio de todos.
     */
    public function home(Request $request, MicroApp $microApp): RedirectResponse
    {
        $request->validate(['on_home' => ['required', 'boolean']]);
        $onHome = $request->boolean('on_home');

        $user = $request->user();
        $hidden = collect($user->preferences['home_hidden'] ?? [])->reject(fn (string $slug) => $slug === $microApp->slug);

        if (! $onHome) {
            $hidden->push($microApp->slug);
        }

        $user->preferences = [...($user->preferences ?? []), 'home_hidden' => $hidden->values()->all()];
        $user->save();

        return back();
    }
}
