<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'title', 'meta_description', 'body', 'nav_group', 'is_indexable'])]
class Page extends Model
{
    protected $attributes = [
        'is_indexable' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_indexable' => 'boolean',
        ];
    }
}
