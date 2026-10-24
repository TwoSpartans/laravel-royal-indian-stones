<?php

namespace Tests\Feature;

use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class PayrollGuardTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_payslips_withhold_calculations_and_cannot_be_marked_sent(): void
    {
        $hr = User::factory()->adminHr()->create();
        $employee = User::factory()->employee()->create();

        $this->signIn($hr)->post('/portal/payroll/prepare', [
            'user_id' => $employee->id,
            'period' => '2026-10',
        ])->assertRedirect();

        $payslip = Payslip::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('review_required', $payslip->status);
        $this->assertSame('withheld', $payslip->summary['calculations']);
        $this->assertNull($payslip->summary['net_paise']);
        $this->assertSame('none', $payslip->payment_state);

        $this->signIn($employee)->get(route('portal.payroll.show', $payslip))->assertNotFound();
        $this->signIn($hr)->post(route('portal.payroll.payment', $payslip), [
            'payment_state' => 'sent',
        ])->assertSessionHasErrors('payment_state');
        $this->assertSame('none', $payslip->fresh()->payment_state);

        $this->signIn($hr)->post(route('portal.payroll.publish', $payslip))->assertRedirect();
        $this->signIn($employee)->get(route('portal.payroll.show', $payslip))
            ->assertOk()
            ->assertSee('Not calculated')
            ->assertSee('not a bank payout');
    }

    public function test_a_manager_cannot_open_a_salary_record(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create();
        $hr = User::factory()->adminHr()->create();
        $this->signIn($hr)->post('/portal/payroll/prepare', [
            'user_id' => $employee->id,
            'period' => '2026-10',
        ]);
        $payslip = Payslip::query()->firstOrFail();

        $this->signIn($manager)->get('/portal/payroll')->assertOk();
        $this->signIn($manager)->get(route('portal.payroll.show', $payslip))->assertOk();
        $this->signIn($employee)->get('/portal/payroll')->assertForbidden();
    }
}
