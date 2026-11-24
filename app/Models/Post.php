<?php

namespace App\Models;

use App\Support\ReadingTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['slug', 'title', 'meta_title', 'meta_description', 'published_on', 'image', 'summary', 'body', 'classic_body_backup', 'post_category_id', 'is_published', 'body_document', 'content_source', 'lock_version', 'status', 'scheduled_for', 'schedule_failure_reason', 'tags', 'featured_image_alt', 'view_count'])]
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

    public function readingMinutes(): int
    {
        if ($this->reading_minutes !== null) {
            return max(1, (int) $this->reading_minutes);
        }

        return ReadingTime::minutesFor($this);
    }

    /**
     * A card description made of complete sentences, never a clipped fragment.
     */
    public function cardExcerpt(int $limit = 280): string
    {
        $summary = trim(preg_replace('/\s+/u', ' ', (string) $this->summary) ?? '');
        if ($summary === '') {
            $summary = trim(preg_replace('/\s+/u', ' ', (string) $this->meta_description) ?? '');
        }
        if ($summary === '') {
            return '';
        }

        preg_match_all('/.*?[.!?](?:\s|$)/u', $summary, $matches);
        $sentences = $matches[0] ?? [];
        if ($sentences === []) {
            return $summary;
        }

        $excerpt = '';
        foreach ($sentences as $sentence) {
            $next = trim($excerpt.' '.$sentence);
            if ($excerpt !== '' && mb_strlen($next) > $limit) {
                break;
            }
            $excerpt = $next;
        }

        return trim($excerpt);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $query): void {
                $query->where('status', 'published')->orWhereNull('status');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
            });
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            $affectsReading = $post->reading_minutes === null
                || $post->isDirty(['body', 'summary', 'body_document', 'content_source']);
            if ($affectsReading) {
                $post->reading_minutes = ReadingTime::minutesFor($post);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'is_published' => 'boolean',
            'body_document' => 'array',
            'tags' => 'array',
            'scheduled_for' => 'datetime',
            'reading_minutes' => 'integer',
            'view_count' => 'integer',
        ];
    }
}
