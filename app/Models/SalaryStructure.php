<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'effective_on', 'components', 'notes', 'created_by'])]
class SalaryStructure extends Model
{
    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'components' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
