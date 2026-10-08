<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_general_portal_information_without_authenticated_workspace_markup(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Platform terpadu')
            ->assertSee('Masuk ke Portal')
            ->assertSee('Data Pegawai')
            ->assertSee('WLA (Workload Analysis)')
            ->assertSee('Fit & Proper')
            ->assertSee('Mutasi')
            ->assertSee('Promosi')
            ->assertSee('Demosi')
            ->assertSee('Formasi')
            ->assertSee('Definitif')
            ->assertSee('Laporan')
            ->assertSee('Cara masuk')
            ->assertDontSee('PILIH UNIT')
            ->assertDontSee('id="pilih-unit"')
            ->assertDontSee('id="workspace"')
            ->assertDontSee('HC Planning & Development')
            ->assertDontSee('HC OD & Career Management')
            ->assertDontSee('Performance & Talent Management')
            ->assertSee('data-login-dialog', false)
            ->assertSee('Selamat datang kembali')
            ->assertSee('name="_token"', false);
    }

    public function test_authenticated_user_sees_units_workspaces_and_logout_instead_of_login_cta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('HC Planning & Development')
            ->assertSee('HC OD & Career Management')
            ->assertSee('Performance & Talent Management')
            ->assertSee('Keluar')
            ->assertDontSee('Masuk ke Portal')
            ->assertDontSee('Data Pegawai');
    }

    public function test_guest_is_sent_to_login_when_opening_a_workspace(): void
    {
        $workspaceUrl = route('portal.workspace', 'hc-od-career-management');

        $this->get($workspaceUrl)
            ->assertRedirectToRoute('login')
            ->assertSessionHas('url.intended', $workspaceUrl);
    }

    public function test_authenticated_user_is_sent_to_the_dashboard_from_a_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertRedirectToRoute('dashboard');
    }

    public function test_authenticated_user_gets_a_404_for_an_unknown_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('portal.workspace', 'unknown-workspace'))
            ->assertNotFound();
    }

    public function test_logout_returns_the_user_to_the_public_portal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('PILIH UNIT');
    }
}
