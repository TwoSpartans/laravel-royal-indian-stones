<?php

namespace Tests\Feature;

use App\Mail\PortalInvitationMail;
use App\Models\AttendancePunch;
use App\Models\Department;
use App\Models\PortalInvitation;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class PortalInvitationTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_an_invitation_sets_a_password_once_and_login_is_not_attendance(): void
    {
        Mail::fake();
        $hr = User::factory()->adminHr()->create();
        $manager = User::factory()->manager()->create();
        $url = $this->invite($hr, $manager, 'employee', 'new.hand@example.com');

        $this->leavePortal();
        $this->get($url)->assertOk()->assertSee('Set your password');
        $this->post($url, [
            'password' => 'Yard-Stone-2026',
            'password_confirmation' => 'Yard-Stone-2026',
            'acknowledge' => '1',
        ])->assertRedirect(route('portal.home'));

        $person = User::query()->where('email', 'new.hand@example.com')->firstOrFail();
        $this->assertNotNull($person->password_set_at);
        $this->assertNotNull($person->profile?->data_notice_acknowledged_at);
        $this->assertSame(0, AttendancePunch::query()->where('user_id', $person->id)->count());

        $this->leavePortal();
        $this->get($url)->assertStatus(410);
        $this->post('/portal/login', [
            'email' => 'new.hand@example.com',
            'password' => 'Yard-Stone-2026',
        ])->assertRedirect(route('portal.home'));
        $this->assertSame(0, AttendancePunch::query()->count());
    }

    public function test_employees_cannot_invite_and_hr_cannot_create_an_admin(): void
    {
        Mail::fake();
        $hr = User::factory()->adminHr()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create();

        $this->signIn($employee)->post('/portal/people', $this->payload($manager, 'employee', 'other@example.com'))
            ->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);

        $this->signIn($hr)->post('/portal/people', $this->payload($manager, 'admin_hr', 'admin2@example.com'))
            ->assertRedirect();
        $this->assertNotNull(PortalInvitation::query()->where('email', 'admin2@example.com')->first());
    }

    public function test_a_mail_failure_still_keeps_the_invitation_link(): void
    {
        Event::listen(MessageSending::class, function (): void {
            throw new RuntimeException('The mail transport refused the invitation.');
        });
        $employer = User::factory()->employer()->create();
        $manager = User::factory()->manager()->create();

        $this->signIn($employer)
            ->post('/portal/people', $this->payload($manager, 'employee', 'kept.link@example.com'))
            ->assertRedirect()
            ->assertSessionHas('invitation_url');

        $this->assertNotNull(PortalInvitation::query()->where('email', 'kept.link@example.com')->first());
    }

    public function test_the_employer_can_invite_and_revoke_admin_hr(): void
    {
        Mail::fake();
        $employer = User::factory()->employer()->create();
        $manager = User::factory()->manager()->create();
        $this->invite($employer, $manager, 'admin_hr', 'hr.lead@example.com');

        $admin = User::query()->where('email', 'hr.lead@example.com')->firstOrFail();
        $admin->forceFill(['password_set_at' => now(), 'deactivated_at' => null])->save();

        $this->signIn($employer)->post(route('portal.people.deactivate', $admin))
            ->assertRedirect();
        $admin->refresh();
        $this->assertNotNull($admin->deactivated_at);

        $this->leavePortal();
        $this->post('/portal/login', [
            'email' => 'hr.lead@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    private function invite(User $actor, User $manager, string $role, string $email): string
    {
        $url = null;
        $this->signIn($actor)->post('/portal/people', $this->payload($manager, $role, $email))->assertRedirect();
        Mail::assertSent(PortalInvitationMail::class, function (PortalInvitationMail $mail) use (&$url, $email): bool {
            $url = $mail->acceptUrl;

            return $mail->hasTo($email);
        });
        $this->assertIsString($url);

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $manager, string $role, string $email): array
    {
        return [
            'name' => 'New Hand',
            'email' => $email,
            'portal_role' => $role,
            'job_title' => 'Saw operator',
            'department_id' => Department::query()->where('name', 'Processing')->value('id'),
            'work_location_id' => WorkLocation::query()->where('name', 'Processing Yard')->value('id'),
            'shift_id' => Shift::query()->where('name', 'General')->value('id'),
            'manager_id' => $role === 'employee' ? $manager->id : null,
            'joined_on' => '2026-10-01',
        ];
    }
}
