<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'introduction', 'image', 'position'])]
class Category extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function searchDescription(): string
    {
        $summary = trim((string) $this->description);
        if ($summary !== '') {
            return $summary;
        }

        return 'Browse the '.$this->name.' collection from Royal Indian Stones.';
    }
}
