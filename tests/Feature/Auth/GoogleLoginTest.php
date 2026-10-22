<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'id-de-prueba',
            'services.google.client_secret' => 'secreto-de-prueba',
        ]);
    }

    private function googleReturns(string $email, string $id = 'google-123', bool $verified = true): void
    {
        $user = (new SocialiteUser)
            ->setRaw(['email_verified' => $verified])
            ->map(['id' => $id, 'email' => $email, 'name' => 'Ana Pérez']);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    public function test_without_credentials_the_button_is_hidden_and_the_routes_are_404()
    {
        config(['services.google.client_id' => null]);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('googleLoginEnabled', false));
        $this->get(route('google.redirect'))->assertNotFound();
        $this->get(route('google.callback'))->assertNotFound();
    }

    public function test_the_button_sends_to_google()
    {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('googleLoginEnabled', true));

        $this->get(route('google.redirect'))
            ->assertRedirectContains('accounts.google.com');
    }

    public function test_a_new_google_account_creates_a_verified_user_without_password()
    {
        $this->googleReturns('Ana@Example.com');

        $this->get(route('google.callback'))->assertRedirect(route('home'));

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Ana Pérez', $user->name);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_an_existing_account_is_linked_by_email_and_keeps_its_password_and_role()
    {
        $user = User::factory()->unverified()->create(['email' => 'ana@example.com']);
        $user->forceFill(['is_admin' => true])->save();
        $hash = $user->password;
        $this->googleReturns('ana@example.com');

        $this->get(route('google.callback'))->assertRedirect(route('home'));

        $user->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame($hash, $user->password);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, User::count());
    }

    public function test_a_returning_account_is_recognized_by_google_id_even_if_the_email_changed()
    {
        $user = User::factory()->create(['email' => 'vieja@example.com']);
        $user->forceFill(['google_id' => 'google-123'])->save();
        $this->googleReturns('nueva@example.com');

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_unverified_google_emails_are_rejected()
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $this->googleReturns('ana@example.com', verified: false);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Esa cuenta de Google no tiene el email verificado.');

        $this->assertGuest();
        $this->assertNull(User::first()->google_id);
    }

    public function test_the_rejection_is_shown_on_the_login_page()
    {
        $this->withSession(['error' => 'No pudimos entrar con Google.'])
            ->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('error', 'No pudimos entrar con Google.'));
    }

    public function test_google_does_not_skip_the_second_factor()
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->forceFill([
            'two_factor_secret' => encrypt('secreto'),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->googleReturns('ana@example.com');

        $this->get(route('google.callback'))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);

        $this->assertGuest();
    }

    public function test_cancelling_on_google_goes_back_to_login_quietly()
    {
        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('error');
    }

    public function test_signed_in_users_cannot_restart_the_flow()
    {
        $location = $this->actingAs(User::factory()->create())
            ->get(route('google.redirect'))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringNotContainsString('google.com', (string) $location);
    }

    public function test_google_only_accounts_reach_security_and_create_their_first_password()
    {
        $user = User::factory()->create(['password' => null]);
        $this->actingAs($user);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('auth.hasPassword', false));

        // Sin contraseña no hay nada que reconfirmar.
        $this->get(route('security.edit'))->assertOk();

        $this->put(route('user-password.update'), [
            'current_password' => 'lo-que-sea',
            'password' => 'una-clave-nueva-123',
            'password_confirmation' => 'una-clave-nueva-123',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('user-password.update'), [
            'password' => 'una-clave-nueva-123',
            'password_confirmation' => 'una-clave-nueva-123',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->password);
    }

    public function test_accounts_with_a_password_still_have_to_confirm_it()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_google_only_accounts_can_be_deleted_without_a_password()
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->delete(route('profile.destroy'))->assertSessionHasNoErrors();

        $this->assertModelMissing($user);
    }
}
