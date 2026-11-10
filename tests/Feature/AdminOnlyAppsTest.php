<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MicroApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOnlyAppsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_guests_and_regular_users_do_not_see_admin_only_apps()
    {
        MicroApp::factory()->create();
        MicroApp::factory()->adminOnly()->create();

        foreach (['home', 'tools'] as $route) {
            $this->get(route($route))
                ->assertInertia(fn (Assert $page) => $page->has('apps', 1));

            $this->actingAs(User::factory()->create())
                ->get(route($route))
                ->assertInertia(fn (Assert $page) => $page->has('apps', 1));

            auth()->logout();
        }
    }

    public function test_admins_see_admin_only_apps()
    {
        MicroApp::factory()->create();
        MicroApp::factory()->adminOnly()->create();
        $admin = $this->admin();

        foreach (['home', 'tools'] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('apps', 2)
                    ->where('apps.1.adminOnly', true)
                );
        }
    }

    public function test_only_admins_can_open_admin_only_apps()
    {
        $app = MicroApp::factory()->adminOnly()->create();

        $this->post(route('apps.open', $app))->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('apps.open', $app))->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('apps.remember', $app))->assertNotFound();

        $this->actingAs($this->admin())
            ->post(route('apps.open', $app))
            ->assertRedirect($app->target);
    }

    public function test_admin_only_apps_are_not_offered_in_the_contact_form()
    {
        MicroApp::factory()->create();
        MicroApp::factory()->adminOnly()->create();

        $this->actingAs($this->admin())
            ->get(route('help'))
            ->assertInertia(fn (Assert $page) => $page->has('appOptions', 1));
    }

    public function test_admins_can_create_an_admin_only_app()
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.apps.store'), [
            'name' => 'Privada',
            'category_id' => $category->id,
            'icon' => 'ph-lock',
            'hue' => 140,
            'target_type' => MicroApp::TARGET_EXTERNAL,
            'target' => 'https://example.com',
            'admin_only' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(MicroApp::where('name', 'Privada')->value('admin_only'));
    }
}
