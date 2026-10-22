<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\MicroApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_launcher_with_visible_apps_in_order()
    {
        $second = MicroApp::factory()->create(['name' => 'Segunda', 'sort_order' => 2]);
        $first = MicroApp::factory()->create(['name' => 'Primera', 'sort_order' => 1]);
        MicroApp::factory()->hidden()->create(['sort_order' => 3]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('apps', 2)
                ->where('apps.0.id', $first->slug)
                ->where('apps.1.id', $second->slug)
                ->where('auth.user', null)
                ->where('recents', null)
            );
    }

    public function test_public_pages_render()
    {
        MicroApp::factory()->create();

        $this->get(route('tools', ['q' => 'pelis']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tools')->has('apps', 1)->where('initialQuery', 'pelis'));
        $this->get(route('about'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('About'));
        $this->get(route('help'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Help')->has('appOptions', 1));
    }

    public function test_opening_an_external_app_redirects_to_its_url_and_records_the_recent()
    {
        $user = User::factory()->create();
        $app = MicroApp::factory()->create(['target' => 'https://agendaflex.example']);

        $this->actingAs($user)
            ->post(route('apps.open', $app))
            ->assertRedirect('https://agendaflex.example');

        $this->assertDatabaseHas('app_user_recents', ['user_id' => $user->id, 'micro_app_id' => $app->id]);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('recents', [$app->slug]));
    }

    public function test_opening_an_external_app_from_inertia_forces_a_full_page_visit()
    {
        $app = MicroApp::factory()->create(['target' => 'https://agendaflex.example']);

        $this->post(route('apps.open', $app), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://agendaflex.example');
    }

    public function test_opening_an_internal_app_redirects_inside_the_site()
    {
        $app = MicroApp::factory()->internal('/apps/billetera')->create();

        $this->post(route('apps.open', $app))->assertRedirect(url('/apps/billetera'));
    }

    public function test_an_app_without_target_goes_back_with_a_notice()
    {
        $app = MicroApp::factory()->create(['target' => null]);

        $this->from(route('home'))
            ->post(route('apps.open', $app))
            ->assertRedirect(route('home'));
    }

    public function test_hidden_apps_cannot_be_opened()
    {
        $app = MicroApp::factory()->hidden()->create();

        $this->post(route('apps.open', $app))->assertNotFound();
    }

    public function test_recents_keep_the_last_four_apps_most_recent_first()
    {
        $user = User::factory()->create();
        $apps = MicroApp::factory()->count(5)->create();

        foreach ($apps as $app) {
            $this->travel(1)->seconds();
            $this->actingAs($user)->post(route('apps.open', $app));
        }

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where(
            'recents',
            $apps->reverse()->take(4)->pluck('slug')->values()->all(),
        ));
    }

    public function test_users_can_save_their_wallpaper()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('preferences.update'), ['wallpaper' => 'trama'])->assertRedirect();
        $this->assertSame('trama', $user->fresh()->preferences['wallpaper']);

        $this->actingAs($user)->patch(route('preferences.update'), ['wallpaper' => 'neon'])->assertSessionHasErrors('wallpaper');
    }

    public function test_users_can_choose_between_generic_icons_and_logos()
    {
        $user = User::factory()->create();
        $user->forceFill(['preferences' => ['wallpaper' => 'trama']])->save();

        $this->actingAs($user)->get(route('tools'))
            ->assertInertia(fn (Assert $page) => $page->where('genericIcons', null));

        $this->patch(route('preferences.update'), ['generic_icons' => false])->assertSessionHasNoErrors();
        $this->assertSame(['wallpaper' => 'trama', 'generic_icons' => false], $user->fresh()->preferences);

        $this->get(route('tools'))
            ->assertInertia(fn (Assert $page) => $page->where('genericIcons', false));

        $this->patch(route('preferences.update'), ['generic_icons' => 'quizas'])->assertSessionHasErrors('generic_icons');
    }

    public function test_guests_cannot_save_preferences()
    {
        $this->patch(route('preferences.update'), ['wallpaper' => 'trama'])->assertRedirect(route('login'));
    }

    public function test_the_help_form_stores_the_message()
    {
        $app = MicroApp::factory()->create();

        $this->post(route('help.store'), [
            'email' => 'alguien@example.com',
            'app' => $app->slug,
            'message' => 'Sería genial si pudiera exportar.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas(ContactMessage::class, [
            'email' => 'alguien@example.com',
            'micro_app_id' => $app->id,
        ]);
    }

    public function test_the_help_form_validates_in_spanish()
    {
        $this->post(route('help.store'), ['email' => 'nope', 'message' => ''])
            ->assertSessionHasErrors(['email', 'message']);

        $this->assertStringContainsString('correo', session('errors')->first('email'));
    }
}
