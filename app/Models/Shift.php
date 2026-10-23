<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'starts_at', 'ends_at', 'grace_minutes', 'weekly_off'])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'weekly_off' => 'array',
        ];
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    /**
     * @return list<int>
     */
    public function weeklyOffDays(): array
    {
        return array_map(intval(...), $this->weekly_off ?? [0]);
    }
}
