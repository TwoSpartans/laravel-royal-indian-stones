<?php

namespace App\Models;

use App\Enums\PortalRole;
use App\Notifications\PortalResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'is_site_admin' => true,
        'timezone' => 'Asia/Kolkata',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_site_admin' => 'boolean',
            'portal_role' => PortalRole::class,
            'deactivated_at' => 'datetime',
            'password_set_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_confirmed_at' => 'datetime',
        ];
    }

    public function requiresMfa(): bool
    {
        if (! ($this->portal_role?->requiresMfa() ?? false)) {
            return false;
        }

        return ! ($this->is_site_admin && $this->portal_role?->isCompanyAdmin() === true && config('portal.single_admin'));
    }

    public function recordsOwnAttendance(): bool
    {
        return $this->portal_role?->isCompanyAdmin() !== true;
    }

    public function canOpenPortalSection(string $section): bool
    {
        if ($this->portal_role !== PortalRole::Employee) {
            return true;
        }

        $allowed = $this->profile?->visible_sections;
        if (! is_array($allowed)) {
            $allowed = array_keys(config('portal.employee_sections'));
        }

        return in_array($section, $allowed, true);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(RegisteredDevice::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PortalResetPassword($token));
    }
}
