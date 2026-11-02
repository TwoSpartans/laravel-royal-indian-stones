<?php

namespace App\Jobs;

use App\Services\Sitemap\SitemapPublisher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RebuildSitemapGroup implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public int $uniqueFor = 180;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(public string $group) {}

    public function uniqueId(): string
    {
        return $this->group;
    }

    public function handle(SitemapPublisher $publisher): void
    {
        try {
            if ($this->group === 'all') {
                $publisher->rebuildAll('event');

                return;
            }

            $publisher->rebuild($this->group, 'event');
        } catch (Throwable $exception) {
            if (config('queue.default') === 'sync') {
                report($exception);

                return;
            }

            throw $exception;
        }
    }
}
