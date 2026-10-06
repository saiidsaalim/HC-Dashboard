<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PortalLandingTest extends TestCase
{
    public function test_guest_can_see_portal_units_and_login_popup(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Platform terpadu')
            ->assertSee('Health Services & Industrial Hygiene')
            ->assertSee('HC Planning & Development')
            ->assertSee('Performance & Talent Management')
            ->assertSee('Workspace unit ini belum tersedia.')
            ->assertSee('data-login-dialog', false)
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('name="remember"', false)
            ->assertSee(asset('images/logo-tonasa.png'), false)
            ->assertSee(asset('images/logo-sig-white.png'), false);
    }

    public function test_authenticated_users_are_redirected_to_dashboard_from_portal(): void
    {
        $user = User::factory()->make();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_validation_errors_reopen_the_portal_popup(): void
    {
        $response = $this->followingRedirects()->from('/')->post('/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertSee('data-auto-open="true"', false)
            ->assertSee('not-an-email')
            ->assertSee('portal-email-error');
    }
}
