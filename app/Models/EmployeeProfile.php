<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'employee_code',
    'department_id',
    'work_location_id',
    'manager_id',
    'shift_id',
    'job_title',
    'employment_status',
    'joined_on',
    'probation_ends_on',
    'phone',
    'address',
    'identity_document_label',
    'identity_document_number',
    'offboarded_on',
    'data_notice_acknowledged_at',
    'data_notice_version',
])]
class EmployeeProfile extends Model
{
    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'joined_on' => 'date',
            'probation_ends_on' => 'date',
            'offboarded_on' => 'date',
            'data_notice_acknowledged_at' => 'datetime',
            'address' => 'encrypted',
            'identity_document_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class, 'user_id', 'user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'user_id', 'user_id');
    }

    public function onboardingItems(): HasMany
    {
        return $this->hasMany(OnboardingItem::class, 'user_id', 'user_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(EmploymentChange::class, 'user_id', 'user_id');
    }
}
