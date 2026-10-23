<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'category',
    'amount_paise',
    'incurred_on',
    'description',
    'status',
    'receipt_path',
    'receipt_name',
    'manager_decided_by',
    'manager_decided_at',
    'hr_decided_by',
    'hr_decided_at',
    'decision_note',
    'payment_state',
    'payment_note',
    'payment_recorded_by',
    'payment_recorded_at',
])]
class ExpenseClaim extends Model
{
    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'incurred_on' => 'date',
            'manager_decided_at' => 'datetime',
            'hr_decided_at' => 'datetime',
            'payment_recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
