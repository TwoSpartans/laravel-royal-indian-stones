<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'punch_type', 'punched_at', 'timezone', 'access_event_id', 'is_correction', 'correction_id', 'superseded_at', 'note'])]
class AttendancePunch extends Model
{
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'is_correction' => 'boolean',
            'superseded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
