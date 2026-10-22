<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MicroAppRequest;
use App\Http\Resources\MicroAppResource;
use App\Models\Category;
use App\Models\MicroApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MicroAppController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Apps', [
            'apps' => MicroAppResource::collection(MicroApp::ordered()->with('category')->get())->resolve(),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(MicroAppRequest $request): RedirectResponse
    {
        $app = MicroApp::create($request->safe()->except(['logo', 'remove_logo']));

        if ($request->hasFile('logo')) {
            $app->replaceLogo($request->file('logo'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "¡Listo! «{$app->name}» ya está en el Inicio."]);

        return back();
    }

    public function update(MicroAppRequest $request, MicroApp $microApp): RedirectResponse
    {
        $microApp->update($request->safe()->except(['logo', 'remove_logo']));

        if ($request->hasFile('logo')) {
            $microApp->replaceLogo($request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            $microApp->replaceLogo(null);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "«{$microApp->name}» quedó actualizada."]);

        return back();
    }

    public function destroy(MicroApp $microApp): RedirectResponse
    {
        $microApp->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "«{$microApp->name}» ya no está en el catálogo."]);

        return back();
    }

    public function visibility(MicroApp $microApp): RedirectResponse
    {
        $microApp->update(['visible' => ! $microApp->visible]);

        return back();
    }

    /**
     * Sube o baja una app un lugar, intercambiando su orden con la vecina.
     */
    public function move(Request $request, MicroApp $microApp): RedirectResponse
    {
        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        DB::transaction(function () use ($microApp, $direction) {
            $ordered = MicroApp::ordered()->lockForUpdate()->get(['id', 'sort_order']);
            $index = $ordered->search(fn (MicroApp $a) => $a->id === $microApp->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $ordered->has($swap)) {
                return;
            }

            // Renumera todo para que no haya órdenes repetidos y después intercambia.
            $ids = $ordered->pluck('id')->all();
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];

            foreach ($ids as $position => $id) {
                MicroApp::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return back();
    }
}
