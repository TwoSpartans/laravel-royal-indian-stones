<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['slug', 'title', 'meta_description', 'published_on', 'image', 'summary', 'body', 'post_category_id', 'is_published', 'body_document', 'content_source', 'lock_version', 'status', 'scheduled_for', 'tags', 'featured_image_alt'])]
class Post extends Model
{
    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function draft(): HasOne
    {
        return $this->hasOne(PostDraft::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(PostReference::class);
    }

    public function isStudio(): bool
    {
        return $this->content_source === 'studio';
    }

    public function isLive(): bool
    {
        return $this->is_published && $this->status === 'published';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'is_published' => 'boolean',
            'body_document' => 'array',
            'tags' => 'array',
            'scheduled_for' => 'datetime',
        ];
    }
}
