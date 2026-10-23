<?php

namespace App\Services\Portal;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\PortalStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveDecision
{
    public function __construct(private AuditWriter $audit) {}

    public function apply(User $actor, LeaveRequest $leave, string $decision, ?string $note, ?string $ip): void
    {
        DB::transaction(function () use ($actor, $leave, $decision, $note, $ip): void {
            $leave->load('type', 'user');
            $locked = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();

            if ($decision === 'rejected') {
                $locked->forceFill($this->stamp($actor, $locked->status, 'rejected', $note))->save();
            } else {
                $needsHr = (bool) $leave->type?->needs_hr_approval
                    && (float) $locked->days >= (float) $leave->type->hr_threshold_days;

                if ($locked->status === 'pending_manager' && $needsHr) {
                    $locked->forceFill($this->stamp($actor, 'pending_manager', 'pending_hr', $note))->save();
                } else {
                    $this->consumeBalance($locked);
                    $locked->forceFill($this->stamp($actor, $locked->status, 'approved', $note))->save();
                }
            }

            $leave->refresh();
            $this->audit->write($actor, 'leave.'.$leave->status, $leave, [
                'days' => (string) $leave->days,
            ], $ip);

            $leave->user?->notify(new PortalStatusNotification(
                'Leave request updated',
                'Your leave request is now '.$this->label($leave->status).'.',
                route('portal.leave.index'),
            ));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function stamp(User $actor, string $from, string $to, ?string $note): array
    {
        $stamp = $from === 'pending_hr' || $to === 'pending_hr' && $from !== 'pending_manager'
            ? [
                'hr_decided_by' => $actor->id,
                'hr_decided_at' => now(),
            ]
            : [
                'manager_decided_by' => $actor->id,
                'manager_decided_at' => now(),
            ];

        if ($to === 'approved' || $to === 'rejected') {
            $stamp['hr_decided_by'] = $from === 'pending_hr' ? $actor->id : ($stamp['hr_decided_by'] ?? null);
        }

        return [
            'status' => $to,
            'decision_note' => $note,
            ...$stamp,
        ];
    }

    private function consumeBalance(LeaveRequest $leave): void
    {
        if (! $leave->type?->is_paid) {
            return;
        }

        $balance = LeaveBalance::query()
            ->where('user_id', $leave->user_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', (int) $leave->starts_on->year)
            ->lockForUpdate()
            ->first();

        if ($balance === null || $balance->remaining() < (float) $leave->days) {
            throw ValidationException::withMessages([
                'decision' => 'The leave balance is no longer enough to approve this request.',
            ]);
        }

        $balance->forceFill([
            'used' => round((float) $balance->used + (float) $leave->days, 1),
        ])->save();
    }

    private function label(string $status): string
    {
        return match ($status) {
            'pending_manager' => 'waiting for the manager',
            'pending_hr' => 'waiting for HR',
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => $status,
        };
    }
}
