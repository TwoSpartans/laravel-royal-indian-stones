<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'leave_type_id', 'year', 'allocated', 'used'])]
class LeaveBalance extends Model
{
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated' => 'decimal:1',
            'used' => 'decimal:1',
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

    public function remaining(): float
    {
        return round((float) $this->allocated - (float) $this->used, 1);
    }
}
