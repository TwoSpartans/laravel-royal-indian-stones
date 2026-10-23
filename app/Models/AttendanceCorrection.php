<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'work_date', 'requested_in', 'requested_out', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'])]
class AttendanceCorrection extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
