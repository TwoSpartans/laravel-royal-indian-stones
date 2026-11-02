<?php

namespace App\Services\Sitemap;

use App\Enums\SitemapGroup;
use App\Jobs\RebuildSitemapGroup;
use App\Models\Category;
use App\Models\Faq;
use App\Models\HomeImage;
use App\Models\HomeSection;
use App\Models\JournalMedia;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

final class SitemapRefresher
{
    /** @var array<string, true> */
    private array $pending = [];

    private bool $hooked = false;

    private bool $flushing = false;

    public function touch(Model $model): void
    {
        foreach ($this->groups($model) as $group) {
            $this->pending[$group->value] = true;
        }

        if ($this->hooked) {
            return;
        }

        $this->hooked = true;
        App::terminating(function (): void {
            $this->flush();
        });
    }

    public function flush(): void
    {
        if ($this->flushing) {
            return;
        }

        $this->flushing = true;
        try {
            while ($this->pending !== []) {
                $groups = array_keys($this->pending);
                $this->pending = [];
                foreach ($groups as $group) {
                    $pending = RebuildSitemapGroup::dispatch($group)
                        ->delay(now()->addSeconds(max(0, (int) config('sitemap.debounce_seconds'))));
                    if (! app()->runningUnitTests()) {
                        $pending->afterCommit();
                    }
                }
            }
        } finally {
            $this->flushing = false;
        }
    }

    /**
     * @return list<SitemapGroup>
     */
    private function groups(Model $model): array
    {
        return match ($model::class) {
            Product::class => [SitemapGroup::Products, SitemapGroup::Pages, SitemapGroup::Images],
            ProductImage::class, JournalMedia::class => [SitemapGroup::Images],
            Category::class => [SitemapGroup::Categories, SitemapGroup::Pages, SitemapGroup::Images],
            Page::class => [SitemapGroup::Pages, SitemapGroup::Images],
            Post::class => [SitemapGroup::Journal, SitemapGroup::Pages, SitemapGroup::Images],
            HomeImage::class, Project::class => [SitemapGroup::Pages, SitemapGroup::Images],
            HomeSection::class, Faq::class => [SitemapGroup::Pages],
            default => [],
        };
    }
}
