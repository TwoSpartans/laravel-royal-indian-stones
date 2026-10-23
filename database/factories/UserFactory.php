<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\PortalRole;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function employer(): static
    {
        return $this->afterCreating(fn (User $user) => $this->openPortal($user, PortalRole::Employer, 'Proprietor', null, true));
    }

    public function adminHr(): static
    {
        return $this->afterCreating(fn (User $user) => $this->openPortal($user, PortalRole::AdminHr, 'HR manager', null, true));
    }

    public function manager(): static
    {
        return $this->afterCreating(fn (User $user) => $this->openPortal($user, PortalRole::Manager, 'Yard manager', null, false));
    }

    public function employee(?User $manager = null): static
    {
        return $this->afterCreating(fn (User $user) => $this->openPortal($user, PortalRole::Employee, 'Yard associate', $manager, false));
    }

    public function deactivated(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['deactivated_at' => now()])->save();
        });
    }

    public function withoutNotice(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->profile?->forceFill([
                'data_notice_acknowledged_at' => null,
                'data_notice_version' => null,
            ])->save();
        });
    }

    private function openPortal(User $user, PortalRole $role, string $title, ?User $manager, bool $mfa): void
    {
        $location = WorkLocation::query()->firstOrCreate(
            ['name' => 'Jodhpur Head Office'],
            ['kind' => 'office', 'timezone' => 'Asia/Kolkata'],
        );
        $department = Department::query()->firstOrCreate(['name' => 'Administration']);
        $shift = Shift::query()->firstOrCreate(
            ['name' => 'General'],
            ['starts_at' => '09:30:00', 'ends_at' => '18:30:00', 'grace_minutes' => 10, 'weekly_off' => [0]],
        );

        $user->forceFill([
            'portal_role' => $role,
            'is_site_admin' => false,
            'password_set_at' => now(),
            'timezone' => 'Asia/Kolkata',
        ])->save();

        if ($mfa) {
            $user->forceFill([
                'mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
                'mfa_confirmed_at' => now(),
                'mfa_recovery_codes' => [],
            ])->save();
        }

        EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'RIS-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'department_id' => $department->id,
            'work_location_id' => $location->id,
            'manager_id' => $manager?->id,
            'shift_id' => $shift->id,
            'job_title' => $title,
            'employment_status' => EmploymentStatus::Active,
            'joined_on' => '2024-04-01',
            'data_notice_acknowledged_at' => now(),
            'data_notice_version' => config('portal.notice_version'),
        ]);

        foreach (LeaveType::query()->where('is_active', true)->get() as $type) {
            LeaveBalance::query()->create([
                'user_id' => $user->id,
                'leave_type_id' => $type->id,
                'year' => (int) now()->year,
                'allocated' => $type->is_paid ? $type->annual_allowance : 0,
                'used' => 0,
            ]);
        }
    }
}
