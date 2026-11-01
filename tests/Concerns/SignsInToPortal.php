<?php

namespace Tests\Concerns;

use App\Models\AttendancePunch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

trait SignsInToPortal
{
    protected function signIn(User $user): static
    {
        $session = [];
        if ($user->requiresMfa()) {
            $session['portal.mfa_user'] = $user->id;
        }

        return $this->actingAs($user)->withSession($session);
    }

    protected function leavePortal(): void
    {
        Auth::logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    /**
     * @return array{punch_type: string, working_on: string, completed_work: string, planned_work: string}
     */
    protected function clockPayload(string $type): array
    {
        if ($type === 'out') {
            return [
                'punch_type' => 'out',
                'working_on' => 'Rajnagar slabs',
                'completed_work' => 'Finished the inspection',
                'planned_work' => 'Load the container tomorrow',
            ];
        }

        return [
            'punch_type' => 'in',
            'working_on' => 'Rajnagar slabs',
            'completed_work' => 'Packed the lot yesterday',
            'planned_work' => 'Inspect the new lot today',
        ];
    }

    protected function insertBreak(User $user, string $start, string $end): void
    {
        foreach (['break_start' => $start, 'break_end' => $end] as $punchType => $at) {
            AttendancePunch::query()->create([
                'user_id' => $user->id,
                'punch_type' => $punchType,
                'punched_at' => Carbon::parse($at, 'Asia/Kolkata'),
                'timezone' => 'Asia/Kolkata',
            ]);
        }
    }
}
