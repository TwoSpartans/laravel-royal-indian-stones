<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class PostCategory extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
