<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePunch;
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
        $this->signIn($employee)->get('/portal/attendance?date=2026-10-21')->assertOk()->assertSee('Late');

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

    public function test_clock_in_is_blocked_until_the_data_notice_is_accepted(): void
    {
        $employee = User::factory()->employee()->withoutNotice()->create();

        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])
            ->assertRedirect(route('portal.notice'));
        $this->assertSame(0, AttendancePunch::query()->count());
    }
}
