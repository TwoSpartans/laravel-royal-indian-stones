<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'leave_type_id',
    'starts_on',
    'ends_on',
    'days',
    'reason',
    'status',
    'manager_decided_by',
    'manager_decided_at',
    'hr_decided_by',
    'hr_decided_at',
    'decision_note',
])]
class LeaveRequest extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'days' => 'decimal:1',
            'manager_decided_at' => 'datetime',
            'hr_decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
