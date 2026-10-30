<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_an_attendance_file_summarises_hours_and_starts_at_the_first_clock_in(): void
    {
        $employer = User::factory()->employer()->create();
        $employee = User::factory()->employee()->create(['name' => 'Karan Kumar']);
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'break_start'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 12:30:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'break_end'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 18:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'out'])->assertRedirect();

        $csv = $this->signIn($employer)->post('/portal/reports', [
            'type' => 'attendance',
            'from' => '2026-10-19',
            'to' => '2026-10-21',
            'format' => 'csv',
        ]);
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('No clock-ins before 2026-10-21', $content);
        $this->assertStringContainsString('Hours worked', $content);
        $this->assertStringContainsString('8h 30m', $content);
        $this->assertStringContainsString('Karan Kumar', $content);
        $this->assertStringNotContainsString('2026-10-19', $content);
        $this->assertStringNotContainsString('Minutes after shift start', $content);

        $pdf = $this->signIn($employer)->post('/portal/reports', [
            'type' => 'attendance',
            'from' => '2026-10-19',
            'to' => '2026-10-21',
            'format' => 'pdf',
        ]);
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Hours worked', $pdf->getContent());
        $this->assertStringContainsString('8h 30m', $pdf->getContent());
        $this->assertStringContainsString('Earlier dates are left out', $pdf->getContent());
    }

    public function test_an_open_shift_is_marked_unsettled_in_the_attendance_file(): void
    {
        $employer = User::factory()->employer()->create();
        $employee = User::factory()->employee()->create(['name' => 'Rohan Kanojiya']);
        $this->travelTo(Carbon::parse('2026-10-28 12:29:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-29 09:00:00', 'Asia/Kolkata'));

        $csv = $this->signIn($employer)->post('/portal/reports', [
            'type' => 'attendance',
            'from' => '2026-10-28',
            'to' => '2026-10-28',
            'format' => 'csv',
        ]);
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Unsettled', $content);
        $this->assertStringContainsString('Missed clock-out', $content);
        $this->assertStringContainsString('12:29', $content);
    }

    public function test_the_employer_can_open_a_person_report_and_an_employee_cannot(): void
    {
        $employer = User::factory()->employer()->create();
        $employee = User::factory()->employee()->create(['name' => 'Karan Kumar']);
        $other = User::factory()->employee()->create(['name' => 'Outside Person']);
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'in'])->assertRedirect();
        $this->travelTo(Carbon::parse('2026-10-21 18:00:00', 'Asia/Kolkata'));
        $this->signIn($employee)->post('/portal/attendance/clock', ['punch_type' => 'out'])->assertRedirect();

        $this->signIn($employee)->get('/portal/attendance/report?user_id='.$employee->id)->assertForbidden();

        $this->signIn($employer)->get('/portal/attendance/report?user_id='.$employee->id.'&from=2026-10-21&to=2026-10-21')
            ->assertOk()
            ->assertSee('Karan Kumar')
            ->assertSee('Hours worked')
            ->assertSee('9h 00m');

        $csv = $this->signIn($employer)->get('/portal/attendance/export?kind=employee&user_id='.$employee->id.'&from=2026-10-21&to=2026-10-21');
        $csv->assertOk();
        $this->assertStringContainsString('Karan Kumar', $csv->streamedContent());
        $this->assertStringNotContainsString('Outside Person', $csv->streamedContent());

        $this->signIn($employer)->get('/portal/attendance/compare?employees[]='.$employee->id.'&from=2026-10-21&to=2026-10-21')
            ->assertOk()
            ->assertSee('Choose at least two people');

        $this->signIn($employer)->get('/portal/attendance/compare?employees[]=1&employees[]=2&employees[]=3&employees[]=4&employees[]=5&from=2026-10-21&to=2026-10-21')
            ->assertRedirect()
            ->assertSessionHasErrors('employees');
    }
}
