<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'is_paid', 'annual_allowance', 'needs_hr_approval', 'hr_threshold_days', 'is_active'])]
class LeaveType extends Model
{
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'annual_allowance' => 'decimal:1',
            'needs_hr_approval' => 'boolean',
            'hr_threshold_days' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    public function allowanceForInput(): string
    {
        $formatted = number_format((float) $this->annual_allowance, 1, '.', '');

        return str_contains($formatted, '.0') ? (string) (int) $this->annual_allowance : $formatted;
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
