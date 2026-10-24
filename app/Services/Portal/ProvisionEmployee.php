<?php

namespace App\Services\Portal;

use App\Enums\EmploymentStatus;
use App\Enums\PortalRole;
use App\Mail\PortalInvitationMail;
use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\OnboardingItem;
use App\Models\PortalInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ProvisionEmployee
{
    public function __construct(private AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{user: User, url: string, mailed: bool}
     */
    public function invite(User $actor, array $attributes, PortalRole $role, ?string $ip): array
    {
        $result = DB::transaction(function () use ($actor, $attributes, $role, $ip): array {
            $user = new User;
            $user->forceFill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Str::random(40),
                'is_site_admin' => false,
                'portal_role' => $role,
                'timezone' => 'Asia/Kolkata',
                'email_verified_at' => null,
                'password_set_at' => null,
            ])->save();

            $profile = $this->profile($user, $attributes, EmploymentStatus::Invited);
            $this->balances($user);
            $this->checklist($user, 'onboarding', config('portal.onboarding_items'));

            $plain = Str::random(64);
            PortalInvitation::query()->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'portal_role' => $role->value,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addDays((int) config('portal.invitation_days')),
                'invited_by' => $actor->id,
            ]);

            $this->audit->write($actor, 'staff.invited', $user, [
                'role' => $role->value,
                'employee_code' => $profile->employee_code,
            ], $ip);

            return [
                'user' => $user,
                'url' => route('portal.invitation.show', ['token' => $plain]),
            ];
        });

        $result['mailed'] = $this->deliverInvitation($result['user'], $result['url'], $role);

        return $result;
    }

    private function deliverInvitation(User $user, string $url, PortalRole $role): bool
    {
        try {
            Mail::to($user->email)->send(new PortalInvitationMail($url, $user->name, $role->label()));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function profile(User $user, array $attributes, EmploymentStatus $status): EmployeeProfile
    {
        return EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => $this->nextCode(),
            'department_id' => $attributes['department_id'] ?? null,
            'work_location_id' => $attributes['work_location_id'] ?? null,
            'manager_id' => $attributes['manager_id'] ?? null,
            'shift_id' => $attributes['shift_id'] ?? null,
            'job_title' => $attributes['job_title'],
            'employment_status' => $status,
            'joined_on' => $attributes['joined_on'] ?? null,
            'probation_ends_on' => $attributes['probation_ends_on'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'visible_sections' => is_array($attributes['visible_sections'] ?? null) ? array_values($attributes['visible_sections']) : null,
        ]);
    }

    public function balances(User $user): void
    {
        $year = (int) now()->year;
        LeaveType::query()->where('is_active', true)->each(function (LeaveType $type) use ($user, $year): void {
            LeaveBalance::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                ],
                [
                    'allocated' => $type->is_paid ? $type->annual_allowance : 0,
                    'used' => 0,
                ],
            );
        });
    }

    /**
     * @param  array<int, string>|null  $labels
     */
    public function checklist(User $user, string $phase, ?array $labels): void
    {
        foreach ($labels ?? [] as $label) {
            OnboardingItem::query()->firstOrCreate([
                'user_id' => $user->id,
                'phase' => $phase,
                'label' => $label,
            ]);
        }
    }

    private function nextCode(): string
    {
        $latest = EmployeeProfile::query()->orderByDesc('id')->value('id');
        $number = ((int) $latest) + 1;

        do {
            $code = 'RIS-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (EmployeeProfile::query()->where('employee_code', $code)->exists());

        return $code;
    }
}
