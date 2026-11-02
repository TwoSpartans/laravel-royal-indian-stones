<?php

namespace App\Observers;

use App\Services\Sitemap\SitemapRefresher;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class SitemapObserver
{
    public function saved(Model $model): void
    {
        $this->refresh($model);
    }

    public function deleted(Model $model): void
    {
        $this->refresh($model);
    }

    private function refresh(Model $model): void
    {
        try {
            app(SitemapRefresher::class)->touch($model);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
