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

    /**
     * Every reason the stored title is not the one search uses.
     *
     * @return list<array{key: string, text: string}>
     */
    public function searchTitleIssues(): array
    {
        $stored = trim((string) $this->meta_title);
        if ($stored === '') {
            return [$this->titleIssue('title-empty', 'Add a page title. Search is using the stone name until one is saved.')];
        }

        $issues = [];
        if (mb_strlen($stored) > 70) {
            $issues[] = $this->titleIssue('title-length', 'Shorten the page title. It is longer than 70 characters, so search is using the stone name.');
        }
        if (mb_strtolower($stored) === mb_strtolower($this->name)) {
            $issues[] = $this->titleIssue('title-name', 'Write a page title that says more than the stone name.');
        }
        if ($this->titleRepeatsTradeWords($stored)) {
            $issues[] = $this->titleIssue('title-trade', 'Rewrite the page title. It repeats trade words such as manufacturer and supplier, so search is using the stone name.');
        }

        return $issues;
    }

    public function searchTitleIssue(): ?string
    {
        $issue = $this->searchTitleIssues()[0] ?? null;

        return is_array($issue) ? $issue['text'] : null;
    }

    /**
     * @return array{key: string, text: string}
     */
    private function titleIssue(string $key, string $text): array
    {
        return ['key' => $key, 'text' => $text];
    }

    public function searchDescriptionIssue(): ?string
    {
        $stored = trim((string) $this->meta_description);
        if ($stored === '' || mb_strlen($stored) < 50) {
            return 'Write a search description of at least 50 characters. A shorter one is replaced by a line built from the name.';
        }

        return null;
    }

    public function searchTitle(): string
    {
        if ($this->searchTitleIssue() !== null) {
            return $this->name.' | Royal Indian Stones';
        }

        return trim((string) $this->meta_title);
    }

    public function searchDescription(): string
    {
        if ($this->searchDescriptionIssue() === null) {
            return trim((string) $this->meta_description);
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
