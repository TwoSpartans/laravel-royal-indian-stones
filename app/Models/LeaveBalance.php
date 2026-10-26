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

    public function summary(): string
    {
        return $this->days($this->remaining()).' of '.$this->days((float) $this->allocated).' days left';
    }

    public function allocatedForInput(): string
    {
        return $this->days((float) $this->allocated);
    }

    private function days(float $value): string
    {
        $formatted = number_format($value, 1, '.', '');

        return str_contains($formatted, '.0') ? (string) (int) $value : $formatted;
    }
}
