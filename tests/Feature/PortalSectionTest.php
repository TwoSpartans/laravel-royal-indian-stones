<?php

namespace Tests\Feature;

use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class PortalSectionTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_an_employee_only_sees_the_sections_the_admin_opened(): void
    {
        $employee = User::factory()->employee()->create();
        $employee->profile?->forceFill(['visible_sections' => ['leave']])->save();

        $this->signIn($employee)->get('/portal')
            ->assertOk()
            ->assertDontSee(route('portal.attendance.index'), false)
            ->assertSee(route('portal.leave.index'), false);

        $this->signIn($employee)->get('/portal/attendance')
            ->assertRedirect('/portal')
            ->assertSessionHas('status', 'That section is not open for your account.');

        $this->signIn($employee)->get('/portal/leave')->assertOk();
    }

    public function test_payslips_follow_the_employee_section_choice(): void
    {
        $hr = User::factory()->adminHr()->create();
        $employee = User::factory()->employee()->create();
        $employee->profile?->forceFill(['visible_sections' => ['attendance', 'leave']])->save();

        $this->signIn($hr)->post('/portal/payroll/prepare', [
            'user_id' => $employee->id,
            'period' => '2026-10',
        ])->assertRedirect();
        $payslip = Payslip::query()->where('user_id', $employee->id)->firstOrFail();
        $this->signIn($hr)->post(route('portal.payroll.publish', $payslip))->assertRedirect();

        $this->signIn($employee)->get('/portal')
            ->assertOk()
            ->assertDontSee(route('portal.payslips.index'), false);
        $this->signIn($employee)->get('/portal/payslips')->assertRedirect('/portal');
        $this->signIn($employee)->get('/portal/account')->assertDontSee('Published payslips');
        $this->signIn($employee)->get(route('portal.payroll.show', $payslip))->assertRedirect('/portal');
        $this->signIn($hr)->get('/portal/payroll')->assertOk();
    }

    public function test_an_employee_with_payslips_open_can_read_a_published_payslip(): void
    {
        $hr = User::factory()->adminHr()->create();
        $employee = User::factory()->employee()->create();
        $this->signIn($hr)->post('/portal/payroll/prepare', [
            'user_id' => $employee->id,
            'period' => '2026-10',
        ]);
        $payslip = Payslip::query()->where('user_id', $employee->id)->firstOrFail();
        $this->signIn($hr)->post(route('portal.payroll.publish', $payslip));

        $this->signIn($employee)->get('/portal')
            ->assertOk()
            ->assertSee(route('portal.payslips.index'), false)
            ->assertDontSee(route('portal.payroll.index'), false);
        $this->signIn($employee)->get('/portal/payslips')
            ->assertOk()
            ->assertSee('2026-10');
        $this->signIn($employee)->get('/portal/account')->assertSee('2026-10');
        $this->signIn($employee)->get('/portal/payroll')->assertForbidden();
    }

    public function test_the_employer_can_save_a_yard_section_list(): void
    {
        $employer = User::factory()->employer()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create();
        $profile = $employee->profile;

        $this->signIn($employer)->put(route('portal.people.update', $employee), [
            'name' => $employee->name,
            'email' => $employee->email,
            'portal_role' => 'employee',
            'job_title' => $profile->job_title,
            'department_id' => $profile->department_id,
            'work_location_id' => $profile->work_location_id,
            'shift_id' => $profile->shift_id,
            'manager_id' => $manager->id,
            'joined_on' => $profile->joined_on->toDateString(),
            'employment_status' => $profile->employment_status->value,
            'sections_chosen' => '1',
            'visible_sections' => ['attendance', 'leave'],
        ])->assertRedirect();

        $profile->refresh();
        $this->assertEqualsCanonicalizing(['attendance', 'leave'], $profile->visible_sections);
        $this->assertNotNull($profile->sections_set_at);

        $this->signIn($employer)->get(route('portal.people.show', $employee))
            ->assertOk()
            ->assertSee('Sections they can open')
            ->assertSee('Open')
            ->assertSee('Closed')
            ->assertSee('Last changed');

        $this->signIn($employee)->get('/portal/attendance')->assertOk();
        $this->signIn($employee)->get('/portal/tasks')->assertRedirect('/portal');
        $this->signIn($manager)->get('/portal/payroll')->assertOk();
        $this->signIn($manager)->get(route('portal.people.show', $employee))
            ->assertSee('Sections they can open');
    }
}
