<?php

namespace App\Services\Portal;

use App\Models\AttendanceCorrection;
use App\Models\AttendancePunch;
use App\Models\User;
use App\Notifications\PortalStatusNotification;
use App\Support\PortalTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CorrectionDecision
{
    public function __construct(private AuditWriter $audit) {}

    public function apply(User $actor, AttendanceCorrection $correction, string $decision, ?string $note, ?string $ip): void
    {
        DB::transaction(function () use ($actor, $correction, $decision, $note, $ip): void {
            $correction->load('user.profile.location');
            if ($decision === 'rejected') {
                $correction->forceFill([
                    'status' => 'rejected',
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'decision_note' => $note,
                ])->save();
            } else {
                $timezone = PortalTime::timezone($correction->user);
                $date = $correction->work_date->toDateString();
                AttendancePunch::query()
                    ->where('user_id', $correction->user_id)
                    ->whereNull('superseded_at')
                    ->orderBy('id')
                    ->get()
                    ->filter(fn (AttendancePunch $punch) => $punch->punched_at->copy()->timezone($timezone)->toDateString() === $date)
                    ->each(fn (AttendancePunch $punch) => $punch->forceFill(['superseded_at' => now()])->save());

                $this->insert($correction, 'in', $correction->requested_in, $date, $timezone);
                $this->insert($correction, 'out', $correction->requested_out, $date, $timezone);

                $correction->forceFill([
                    'status' => 'approved',
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'decision_note' => $note,
                ])->save();
            }

            $this->audit->write($actor, 'attendance.correction_'.$correction->status, $correction, [
                'work_date' => $correction->work_date->toDateString(),
            ], $ip);

            $correction->user?->notify(new PortalStatusNotification(
                'Attendance correction '.$correction->status,
                'Your attendance correction for '.$correction->work_date->format('d M Y').' was '.$correction->status.'.',
                route('portal.attendance.index'),
            ));
        });
    }

    private function insert(AttendanceCorrection $correction, string $type, ?string $time, string $date, string $timezone): void
    {
        if ($time === null || $time === '') {
            return;
        }

        AttendancePunch::query()->create([
            'user_id' => $correction->user_id,
            'punch_type' => $type,
            'punched_at' => Carbon::parse($date.' '.$time, $timezone),
            'timezone' => $timezone,
            'is_correction' => true,
            'correction_id' => $correction->id,
            'note' => 'Approved correction',
        ]);
    }
}
