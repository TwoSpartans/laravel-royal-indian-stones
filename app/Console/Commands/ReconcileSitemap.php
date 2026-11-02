<?php

namespace App\Console\Commands;

use App\Services\Sitemap\SitemapPublisher;
use App\Services\Sitemap\SitemapValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('sitemap:reconcile')]
#[Description('Rebuild every sitemap and repair anything the event updates missed')]
class ReconcileSitemap extends Command
{
    public function handle(SitemapPublisher $publisher, SitemapValidator $validator): int
    {
        try {
            $publisher->rebuildAll('schedule');
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $result = $validator->validate();
        foreach ($result['errors'] as $error) {
            Log::error('Sitemap validation error', ['message' => $error]);
            $this->error($error);
        }
        foreach ($result['warnings'] as $warning) {
            Log::warning('Sitemap validation warning', ['message' => $warning]);
            $this->warn($warning);
        }

        $this->info('Sitemap reconciliation finished.');

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
