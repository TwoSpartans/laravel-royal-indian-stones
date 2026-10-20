<?php

namespace App\Models;

use App\Support\ResponsivePicture;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
        if ($image?->path) {
            $sources = app(ResponsivePicture::class)->sources($image->path);
            if ($sources['avif'] !== null) {
                $record['avifSrcset'] = $sources['avif'];
            }
            if ($sources['webp'] !== null) {
                $record['webpSrcset'] = $sources['webp'];
            }
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

    public function searchTitle(): string
    {
        $stored = trim((string) $this->meta_title);
        if ($stored !== ''
            && mb_strlen($stored) <= 70
            && mb_strtolower($stored) !== mb_strtolower($this->name)
            && ! $this->titleRepeatsTradeWords($stored)) {
            return $stored;
        }

        return $this->name.' | Royal Indian Stones';
    }

    public function searchDescription(): string
    {
        $stored = trim((string) $this->meta_description);
        if ($stored !== '' && mb_strlen($stored) >= 50) {
            return $stored;
        }

        $sentence = $this->name;
        $material = trim((string) $this->category?->name);
        if ($material !== '') {
            $sentence .= ' is '.$this->indefiniteArticle($material).' '.mb_strtolower($material);
        }
        if (trim((string) $this->color) !== '') {
            $sentence .= ' in '.$this->color;
        }
        $sentence .= ' from Royal Indian Stones';

        $finish = $this->finishes[0] ?? null;
        if (is_string($finish) && trim($finish) !== '') {
            $sentence .= ', supplied with a '.mb_strtolower(trim($finish)).' finish';
        }

        $uses = array_values(array_filter(
            $this->applications ?? [],
            fn (mixed $use): bool => is_string($use) && trim($use) !== '',
        ));
        if ($uses !== []) {
            $sentence .= ', for '.implode(', ', array_slice($uses, 0, 3));
        }

        return $sentence.'.';
    }

    private function titleRepeatsTradeWords(string $title): bool
    {
        $hits = 0;
        $lower = mb_strtolower($title);
        foreach (['manufacturer', 'supplier', 'exporter', 'wholesaler', 'trader'] as $word) {
            if (str_contains($lower, $word)) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    private function indefiniteArticle(string $word): string
    {
        return in_array(mb_strtolower(mb_substr($word, 0, 1)), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
    }
}
