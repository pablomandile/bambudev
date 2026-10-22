<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\MicroApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Help', [
            'appOptions' => MicroApp::visible()->ordered()->get(['slug', 'name'])
                ->map(fn (MicroApp $app) => ['id' => $app->slug, 'name' => $app->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'app' => ['nullable', 'string', 'exists:micro_apps,slug'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        ContactMessage::create([
            'user_id' => $request->user()?->id,
            'micro_app_id' => isset($validated['app'])
                ? MicroApp::where('slug', $validated['app'])->value('id')
                : null,
            'email' => $validated['email'],
            'message' => $validated['message'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => '¡Recibido! Te respondemos antes de que se enfríe el café.']);

        return back();
    }
}
