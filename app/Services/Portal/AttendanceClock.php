<?php

namespace App\Services\Portal;

use App\Models\AttendancePunch;
use App\Models\User;
use App\Support\PortalTime;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceClock
{
    public function __construct(
        private AttendanceBoard $board,
        private RecordAccess $accessEvents,
    ) {}

    public function punch(Request $request, User $user, string $type): AttendancePunch
    {
        $last = $this->board->lastPunch($user);
        $allowed = match ($type) {
            'in' => $last === null || $last->punch_type === 'out',
            'out' => $last !== null && in_array($last->punch_type, ['in', 'break_end'], true),
            'break_start' => $last !== null && $last->punch_type === 'in',
            'break_end' => $last !== null && $last->punch_type === 'break_start',
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'punch_type' => 'That attendance action does not follow the current punch.',
            ]);
        }

        $event = $this->accessEvents->write($request, 'attendance', $user);

        return AttendancePunch::query()->create([
            'user_id' => $user->id,
            'punch_type' => $type,
            'punched_at' => now(),
            'timezone' => PortalTime::timezone($user),
            'access_event_id' => $event->id,
        ]);
    }
}
