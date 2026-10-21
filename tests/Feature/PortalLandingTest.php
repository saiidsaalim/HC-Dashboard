<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalLandingTest extends TestCase
{
    public function test_login_validation_errors_are_shown_on_login_page(): void
    {
        $response = $this->followingRedirects()->from('/login')->post('/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertSee('not-an-email')
            ->assertSee('Selamat datang kembali');
    }
}
