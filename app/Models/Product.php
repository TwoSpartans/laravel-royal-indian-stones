<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'slug', 'color', 'finishes', 'applications', 'summary', 'detail', 'application', 'meta_title', 'meta_description', 'is_published'])]
class Product extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('position');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function catalogueImage(): ?ProductImage
    {
        return $this->images->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogueArray(): array
    {
        $image = $this->catalogueImage();
        $record = [
            'id' => $this->slug,
            'name' => $this->name,
            'url' => '/'.$this->slug,
            'category' => $this->category?->name ?? '',
            'color' => $this->color,
            'finishes' => $this->finishes ?? [],
            'applications' => $this->applications ?? [],
            'image' => $image?->path ?? '',
        ];

        if ($image?->width) {
            $record['imageWidth'] = $image->width;
        }
        if ($image?->height) {
            $record['imageHeight'] = $image->height;
        }
        if ($image?->srcset) {
            $record['imageSrcset'] = $image->srcset;
        }

        return $record;
    }

    /**
     * @return list<string>
     */
    public function paragraphs(?string $text): array
    {
        $parts = preg_split("/\n{2,}/", trim((string) $text)) ?: [];

        return array_values(array_filter(array_map(fn ($part) => trim($part), $parts), fn ($part) => $part !== ''));
    }

    protected function casts(): array
    {
        return [
            'finishes' => 'array',
            'applications' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
