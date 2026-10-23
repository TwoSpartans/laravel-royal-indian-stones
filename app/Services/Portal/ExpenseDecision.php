<?php

namespace App\Services\Portal;

use App\Models\ExpenseClaim;
use App\Models\User;
use App\Notifications\PortalStatusNotification;
use Illuminate\Support\Facades\DB;

class ExpenseDecision
{
    public function __construct(private AuditWriter $audit) {}

    public function apply(User $actor, ExpenseClaim $claim, string $decision, ?string $note, ?string $ip): void
    {
        DB::transaction(function () use ($actor, $claim, $decision, $note, $ip): void {
            $claim = ExpenseClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $from = $claim->status;

            if ($decision === 'rejected') {
                $claim->status = 'rejected';
            } elseif ($from === 'submitted') {
                $claim->status = 'pending_hr';
                $claim->manager_decided_by = $actor->id;
                $claim->manager_decided_at = now();
            } else {
                $claim->status = 'approved';
                $claim->hr_decided_by = $actor->id;
                $claim->hr_decided_at = now();
            }

            $claim->decision_note = $note;
            $claim->save();

            $this->audit->write($actor, 'expense.'.$claim->status, $claim, [
                'amount_paise' => $claim->amount_paise,
            ], $ip);

            $claim->user?->notify(new PortalStatusNotification(
                'Expense claim updated',
                'Your expense claim is now '.$claim->status.'.',
                route('portal.expenses.show', $claim),
            ));
        });
    }

    public function recordArrangement(User $actor, ExpenseClaim $claim, string $note, ?string $ip): void
    {
        $claim->forceFill([
            'payment_state' => 'arranged_outside_system',
            'payment_note' => $note,
            'payment_recorded_by' => $actor->id,
            'payment_recorded_at' => now(),
        ])->save();

        $this->audit->write($actor, 'expense.payment_arranged_outside_system', $claim, [], $ip);

        $claim->user?->notify(new PortalStatusNotification(
            'Expense arrangement recorded',
            'HR recorded that this expense was arranged outside the portal. This is not a confirmed bank payout.',
            route('portal.expenses.show', $claim),
        ));
    }
}
