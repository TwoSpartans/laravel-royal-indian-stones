<?php

namespace Tests\Feature;

use App\Models\ExpenseClaim;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_leave_uses_one_balance_and_a_manager_can_approve_it(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create();
        $hr = User::factory()->adminHr()->create();
        $leave = LeaveType::query()->where('code', 'leave')->firstOrFail();

        $this->signIn($employee)->get('/portal/leave')
            ->assertOk()
            ->assertSee('31 of 31 days left')
            ->assertDontSee('Casual Leave')
            ->assertDontSee('Sick Leave')
            ->assertDontSee('Earned Leave');

        $this->signIn($employee)->post('/portal/leave', [
            'starts_on' => '2026-10-06',
            'ends_on' => '2026-10-06',
            'reason' => 'Family visit',
        ])->assertRedirect();
        $request = LeaveRequest::query()->where('leave_type_id', $leave->id)->firstOrFail();
        $this->assertSame('pending_manager', $request->status);
        $this->signIn($employee)->post(route('portal.leave.decide', $request), ['decision' => 'approved'])->assertForbidden();
        $this->signIn($manager)->post(route('portal.leave.decide', $request), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(1.0, (float) LeaveBalance::query()->where('user_id', $employee->id)->where('leave_type_id', $leave->id)->value('used'));
        $this->signIn($employee)->get('/portal')->assertOk()->assertSee('30 of 31 days left');

        $this->signIn($employee)->post('/portal/leave', [
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-08',
            'reason' => 'Travel after dispatch',
        ])->assertRedirect();
        $longer = LeaveRequest::query()->where('reason', 'Travel after dispatch')->firstOrFail();
        $this->assertSame(4.0, (float) $longer->days);
        $this->signIn($manager)->post(route('portal.leave.decide', $longer), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('pending_hr', $longer->fresh()->status);
        $this->signIn($hr)->post(route('portal.leave.decide', $longer), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $longer->fresh()->status);
        $this->assertSame(5.0, (float) LeaveBalance::query()->where('user_id', $employee->id)->where('leave_type_id', $leave->id)->value('used'));
    }

    public function test_an_expense_is_approved_and_recorded_outside_the_portal(): void
    {
        Storage::fake('portal');
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee($manager)->create();
        $hr = User::factory()->adminHr()->create();

        $this->signIn($employee)->post('/portal/expenses', [
            'category' => 'sample_freight',
            'amount' => '1250.50',
            'incurred_on' => '2026-10-20',
            'description' => 'Sample sandstone freight to the buyer',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect();

        $claim = ExpenseClaim::query()->firstOrFail();
        $this->assertSame(125050, $claim->amount_paise);
        $this->signIn($manager)->post(route('portal.expenses.decide', $claim), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('pending_hr', $claim->fresh()->status);
        $this->signIn($hr)->post(route('portal.expenses.decide', $claim), ['decision' => 'approved'])->assertRedirect();

        $this->signIn($hr)->post(route('portal.expenses.payment', $claim), [
            'payment_state' => 'sent',
            'payment_note' => 'Bank transfer',
        ])->assertSessionHasErrors('payment_state');
        $this->assertSame('none', $claim->fresh()->payment_state);

        $this->signIn($hr)->post(route('portal.expenses.payment', $claim), [
            'payment_state' => 'arranged_outside_system',
            'payment_note' => 'Paid from the yard cash book on 21 Oct.',
        ])->assertRedirect();

        $this->assertSame('arranged_outside_system', $claim->fresh()->payment_state);
        $this->signIn($employee)->get(route('portal.expenses.show', $claim))
            ->assertOk()
            ->assertSee('not a confirmed bank payout')
            ->assertDontSee('Payment sent');
    }
}
