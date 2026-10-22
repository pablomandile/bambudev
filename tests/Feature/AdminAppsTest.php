<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MicroApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAppsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_guests_are_sent_to_login()
    {
        $this->get(route('admin.apps.index'))->assertRedirect(route('login'));
    }

    public function test_non_admins_are_forbidden()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.apps.index'))
            ->assertForbidden();
    }

    public function test_admins_see_every_app_including_hidden_ones()
    {
        MicroApp::factory()->create();
        MicroApp::factory()->hidden()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.apps.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Apps')
                ->has('apps', 2)
                ->has('categories')
                ->where('auth.isAdmin', true)
            );
    }

    public function test_admins_can_publish_an_app_that_shows_up_on_the_launcher()
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.apps.store'), [
            'name' => 'ListaSúper',
            'description' => 'La lista del súper, compartida.',
            'category_id' => $category->id,
            'icon' => 'ph-shopping-cart',
            'hue' => 140,
            'tags' => 'compras super',
            'target_type' => 'internal',
            'target' => '/apps/lista',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $app = MicroApp::firstWhere('name', 'ListaSúper');
        $this->assertSame('listasuper', $app->slug);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('apps.0.id', 'listasuper'));
    }

    public function test_targets_are_validated_by_type()
    {
        $category = Category::factory()->create();
        $payload = [
            'name' => 'Algo',
            'category_id' => $category->id,
            'icon' => 'ph-lightning',
            'hue' => 140,
        ];

        $this->actingAs($this->admin());

        $this->post(route('admin.apps.store'), [...$payload, 'target_type' => 'external', 'target' => 'agendaflex.com.ar'])
            ->assertSessionHasErrors('target');
        $this->post(route('admin.apps.store'), [...$payload, 'target_type' => 'internal', 'target' => 'https://otro.sitio'])
            ->assertSessionHasErrors('target');
        $this->post(route('admin.apps.store'), [...$payload, 'target_type' => 'external', 'target' => '', 'icon' => '<script>'])
            ->assertSessionHasErrors('icon');
    }

    public function test_admins_can_update_hide_and_delete_apps()
    {
        $app = MicroApp::factory()->create();
        $this->actingAs($this->admin());

        $this->put(route('admin.apps.update', $app), [
            'name' => 'Renombrada',
            'category_id' => $app->category_id,
            'icon' => 'ph-gift',
            'hue' => 20,
            'target_type' => 'external',
            'target' => 'https://nueva.example',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Renombrada', $app->fresh()->name);

        $this->patch(route('admin.apps.visibility', $app));
        $this->assertFalse($app->fresh()->visible);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('apps', 0));

        $this->delete(route('admin.apps.destroy', $app));
        $this->assertModelMissing($app);
    }

    public function test_admins_can_reorder_apps()
    {
        [$a, $b, $c] = MicroApp::factory()->count(3)->sequence(
            ['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3],
        )->create()->all();

        $this->actingAs($this->admin());

        $this->patch(route('admin.apps.move', $c), ['direction' => 'up']);
        $this->assertSame([$a->id, $c->id, $b->id], MicroApp::ordered()->pluck('id')->all());

        // El primero no puede subir más.
        $this->patch(route('admin.apps.move', $a), ['direction' => 'up']);
        $this->assertSame([$a->id, $c->id, $b->id], MicroApp::ordered()->pluck('id')->all());
    }

    public function test_non_admins_cannot_change_apps()
    {
        $app = MicroApp::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.apps.destroy', $app))
            ->assertForbidden();

        $this->assertModelExists($app);
    }
}
