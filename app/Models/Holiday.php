<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_location_id', 'name', 'holiday_on'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return [
            'holiday_on' => 'date',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }
}
