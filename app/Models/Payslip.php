<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'period', 'status', 'summary', 'disclaimer', 'payment_state', 'payment_note', 'prepared_by', 'published_at'])]
class Payslip extends Model
{
    protected function casts(): array
    {
        return [
            'summary' => 'encrypted:array',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
