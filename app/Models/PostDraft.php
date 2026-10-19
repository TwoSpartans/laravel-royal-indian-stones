<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['post_id', 'document', 'title', 'slug', 'excerpt', 'meta_description', 'tags', 'featured_image_alt', 'image'])]
class PostDraft extends Model
{
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'tags' => 'array',
        ];
    }
}
