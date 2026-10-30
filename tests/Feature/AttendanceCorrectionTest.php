<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePunch;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_a_late_clock_in_is_attendance_and_a_manager_approves_the_correction(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create();
        $other = User::factory()->manager()->create();
        $this->travelTo(Carbon::parse('2026-10-21 10:00:00', 'Asia/Kolkata'));

        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])
            ->assertRedirect();
        $this->assertSame(1, AttendancePunch::query()->count());
        $this->assertTrue(AccessEvent::query()->where('event_type', 'attendance')->exists());
        $this->signIn($employee)->get('/portal/attendance?date=2026-10-21')->assertOk()->assertSee('Late')->assertSee('30 min late');

        $this->signIn($employee)->post('/portal/attendance/corrections', [
            'work_date' => '2026-10-20',
            'requested_in' => '09:30',
            'requested_out' => '18:30',
            'reason' => 'The yard gate register was missed.',
        ])->assertRedirect();

        $correction = AttendanceCorrection::query()->firstOrFail();
        $this->signIn($employee)->post(route('portal.attendance.corrections.decide', $correction), [
            'decision' => 'approved',
        ])->assertForbidden();
        $this->signIn($other)->post(route('portal.attendance.corrections.decide', $correction), [
            'decision' => 'approved',
            'decision_note' => 'Gate register checked.',
        ])->assertRedirect();
        $this->signIn($manager)->post(route('portal.attendance.corrections.decide', $correction), [
            'decision' => 'approved',
        ])->assertForbidden();

        $correction->refresh();
        $this->assertSame('approved', $correction->status);
        $this->assertTrue(AttendancePunch::query()->where('correction_id', $correction->id)->where('is_correction', true)->exists());
    }

    public function test_an_employee_sees_worked_hours_and_the_employer_does_not(): void
    {
        $employee = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->adminHr()->create();
        $employer = User::factory()->employer()->create();
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'break_start'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:30:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'break_end'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 18:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'out'])->assertRedirect();

        $this->signIn($employee)->get('/portal')->assertOk()->assertSee('Hours worked')->assertSee('8h 30m')->assertSee('No open days');
        $this->signIn($manager)->get('/portal')->assertOk()->assertSee('Hours worked')->assertSee('0h 00m');
        $this->signIn($hr)->get('/portal')->assertOk()->assertSee('Hours worked')->assertSee('0h 00m');
        $this->signIn($employer)->get('/portal')->assertOk()->assertDontSee('Hours worked');
    }

    public function test_a_day_without_clock_out_is_listed_as_unsettled(): void
    {
        $employee = User::factory()->employee()->create();
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'Asia/Kolkata'));

        $this->signIn($employee)->get('/portal')
            ->assertOk()
            ->assertSee('Unsettled working hours')
            ->assertSee('3h 00m')
            ->assertSee('Clock-out was not recorded')
            ->assertSee('0h 00m');
    }

    public function test_the_employer_reviews_a_closed_day_without_clock_buttons(): void
    {
        $employee = User::factory()->employee()->create();
        $employer = User::factory()->employer()->create();
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'break_start'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:30:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'break_end'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 18:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'out'])->assertRedirect();

        $this->signIn($employer)->get('/portal/attendance?date=2026-10-21&user_id='.$employee->id)
            ->assertOk()
            ->assertSee('8h 30m')
            ->assertSee('21 Oct 2026, 09:00')
            ->assertDontSee('Clock in');
    }

    public function test_an_open_day_is_unsettled_and_only_that_date_is_listed(): void
    {
        $employee = User::factory()->employee()->create();
        $employer = User::factory()->employer()->create();
        $this->travelTo(Carbon::parse('2026-10-20 09:15:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-20 18:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'out'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'Asia/Kolkata'));

        $this->signIn($employer)->get('/portal/attendance?date=2026-10-21&user_id='.$employee->id)
            ->assertOk()
            ->assertSee('Unsettled')
            ->assertSee('21 Oct 2026, 09:00')
            ->assertDontSee('20 Oct 2026, 09:15');
    }

    public function test_the_employer_can_reject_a_pending_overtime_request(): void
    {
        $employee = User::factory()->employee()->create();
        $employer = User::factory()->employer()->create();
        $overtime = OvertimeRequest::query()->create([
            'user_id' => $employee->id,
            'work_date' => '2026-10-21',
            'minutes' => 90,
            'reason' => 'Buyer loading ran past the shift.',
            'status' => 'pending',
        ]);
        AttendanceCorrection::query()->create([
            'user_id' => $employee->id,
            'work_date' => '2026-10-19',
            'requested_in' => '09:30',
            'requested_out' => '18:30',
            'reason' => 'Already checked.',
            'status' => 'approved',
        ]);

        $this->signIn($employer)->get('/portal/attendance/exceptions')
            ->assertOk()
            ->assertSee('Reject')
            ->assertSee('Buyer loading ran past the shift.')
            ->assertSee('Waiting for a decision');

        $this->signIn($employer)->post(route('portal.attendance.overtime.decide', $overtime), [
            'decision' => 'rejected',
        ])->assertRedirect();

        $this->assertSame('rejected', $overtime->fresh()->status);
    }

    public function test_the_employer_home_shows_todays_yard_snapshot(): void
    {
        $employer = User::factory()->employer()->create();
        $employee = User::factory()->employee()->create(['name' => 'Karan Kumar']);

        $this->signIn($employer)->get('/portal')
            ->assertOk()
            ->assertSee('Today at the yards')
            ->assertSee('On the roster')
            ->assertSee('Not in yet')
            ->assertSee('Needs your decision')
            ->assertSee('No period prepared')
            ->assertSee('View attendance')
            ->assertDontSee('Hours worked');

        $this->signIn($employer)->get('/portal?location_id='.$employee->profile->work_location_id)
            ->assertOk()
            ->assertSee($employee->profile->location->name);
    }

    public function test_clock_in_is_blocked_until_the_data_notice_is_accepted(): void
    {
        $employee = User::factory()->employee()->withoutNotice()->create();

        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])
            ->assertRedirect(route('portal.notice'));
        $this->assertSame(0, AttendancePunch::query()->count());
    }
}
