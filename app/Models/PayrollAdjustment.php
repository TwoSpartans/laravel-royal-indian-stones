<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'period', 'amount_paise', 'reason', 'status', 'decided_by'])]
class PayrollAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
