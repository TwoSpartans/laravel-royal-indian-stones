<?php

namespace Tests\Feature;

use App\Enums\PortalRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalEmployerBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_studio_admin_can_be_enabled_for_the_staff_portal(): void
    {
        config([
            'portal.employer_name' => 'Employer',
            'portal.employer_email' => 'admin@royalindianstones.com',
            'portal.employer_password' => 'RisAdmin-2026',
        ]);
        User::factory()->create([
            'name' => 'Site admin',
            'email' => 'admin@royalindianstones.com',
            'is_site_admin' => true,
        ]);

        $this->artisan('portal:bootstrap-employer', ['--with-site-admin' => true])
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@royalindianstones.com')->firstOrFail();
        $this->assertSame(PortalRole::Employer, $admin->portal_role);
        $this->assertNotNull($admin->password_set_at);

        $this->get('/portal/login')->assertOk()->assertSee('How to use')->assertSee('There is no public registration.');

        $this->post('/portal/login', [
            'email' => 'admin@royalindianstones.com',
            'password' => 'RisAdmin-2026',
        ])->assertRedirect(route('portal.home'));

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('Staff portal');
        $this->actingAs($admin)->get('/portal')->assertOk()->assertSee('Content studio')->assertSee('How to use')->assertSee('This account stays off attendance and leave')->assertDontSee('Clock in');
        $this->actingAs($admin)->get('/portal/attendance')->assertOk()->assertDontSee('Clock in')->assertSee('does not clock in');
        $this->actingAs($admin)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertForbidden();
        $this->actingAs($admin)->post('/portal/leave', [
            'leave_type_id' => 1,
            'starts_on' => '2026-11-02',
            'ends_on' => '2026-11-02',
            'reason' => 'The employer does not apply.',
        ])->assertForbidden();
    }

    public function test_the_employer_password_may_be_eight_characters(): void
    {
        config([
            'portal.employer_name' => 'Employer',
            'portal.employer_email' => 'admin@royalindianstones.com',
            'portal.employer_password' => 'passwor',
        ]);

        $this->artisan('portal:bootstrap-employer', ['--with-site-admin' => true])
            ->assertFailed();

        config(['portal.employer_password' => 'password']);

        $this->artisan('portal:bootstrap-employer', ['--with-site-admin' => true])
            ->assertSuccessful();

        $this->post('/portal/login', [
            'email' => 'admin@royalindianstones.com',
            'password' => 'password',
        ])->assertRedirect(route('portal.home'));
    }
}
