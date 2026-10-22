<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public const WALLPAPERS = ['papel', 'bambu', 'trama', 'bosque'];

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wallpaper' => ['required', Rule::in(self::WALLPAPERS)],
        ]);

        $user = $request->user();
        $user->preferences = [...($user->preferences ?? []), ...$validated];
        $user->save();

        return back();
    }
}
