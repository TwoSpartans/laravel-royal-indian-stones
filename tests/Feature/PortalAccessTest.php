<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\AttendancePunch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_portal_is_private_and_the_public_site_stays_available(): void
    {
        $this->get('/')->assertOk();
        $this->get('/portal')->assertRedirect('/portal/login')->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/portal/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('noindex, nofollow', false);
        $this->get('/portal/register')->assertNotFound();
        $this->post('/portal/register', ['email' => 'new@example.com'])->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/portal');

        $admin = User::factory()->create();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_staff_cannot_open_another_role_records_or_the_content_studio(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create(['name' => 'Meera Rao']);
        $outsider = User::factory()->employee()->create(['name' => 'Outside Team']);
        $otherManager = User::factory()->manager()->create();

        $this->signIn($employee)->get('/portal/people')->assertForbidden();
        $this->signIn($employee)->get('/portal/people/'.$outsider->id)->assertNotFound();
        $this->signIn($employee)->get('/portal/reports')->assertForbidden();
        $this->signIn($employee)->get('/admin/dashboard')->assertRedirect('/portal');

        $this->signIn($manager)->get('/portal/people')
            ->assertOk()
            ->assertSee('Meera Rao')
            ->assertDontSee('Outside Team');

        $this->signIn($otherManager)->get('/portal/people/'.$employee->id)->assertNotFound();
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $employee = User::factory()->employee()->deactivated()->create([
            'email' => 'former@example.com',
        ]);

        $this->post('/portal/login', [
            'email' => 'former@example.com',
            'password' => 'password',
        ])->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->signIn($employee)->get('/portal')->assertRedirect('/portal/login');
    }

    public function test_signing_in_does_not_mark_attendance(): void
    {
        $employee = User::factory()->employee()->create([
            'email' => 'yard@example.com',
        ]);

        $this->post('/portal/login', [
            'email' => 'yard@example.com',
            'password' => 'password',
        ])->assertRedirect(route('portal.home'));

        $this->assertSame(0, AttendancePunch::query()->count());
        $this->assertTrue(AccessEvent::query()->where('user_id', $employee->id)->where('event_type', 'login')->exists());
        $this->assertFalse(AccessEvent::query()->where('event_type', 'attendance')->exists());
    }

    public function test_privileged_sign_in_stops_for_a_second_factor(): void
    {
        $employer = User::factory()->employer()->create([
            'email' => 'owner@example.com',
        ]);

        $this->post('/portal/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertRedirect(route('portal.mfa.challenge'));

        $this->assertAuthenticatedAs($employer);
        $this->get('/portal')->assertRedirect(route('portal.mfa.challenge'));
        $this->assertSame(0, AttendancePunch::query()->count());
    }

    public function test_manager_export_stays_inside_the_team(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create(['name' => 'Meera Rao']);
        User::factory()->employee()->create(['name' => 'Outside Team']);

        $response = $this->signIn($manager)->post('/portal/reports', [
            'type' => 'headcount',
            'from' => '2026-10-01',
            'to' => '2026-10-07',
            'format' => 'csv',
        ]);
        $response->assertForbidden();

        $csv = $this->signIn($manager)->post('/portal/reports', [
            'type' => 'attendance',
            'from' => '2026-10-21',
            'to' => '2026-10-21',
            'format' => 'csv',
        ]);
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Meera Rao', $content);
        $this->assertStringNotContainsString('Outside Team', $content);
        $this->assertSame($employee->name, 'Meera Rao');
    }
}
