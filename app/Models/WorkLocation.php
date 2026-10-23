<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'kind', 'timezone'])]
class WorkLocation extends Model
{
    public function networks(): HasMany
    {
        return $this->hasMany(OfficeNetwork::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }
}
