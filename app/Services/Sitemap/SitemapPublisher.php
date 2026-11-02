<?php

namespace App\Services\Sitemap;

use App\Enums\SitemapGroup;
use App\Models\SitemapExclusion;
use App\Models\SitemapRun;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SitemapPublisher
{
    public function __construct(
        private SitemapCatalog $catalog,
        private SitemapXml $xml,
        private SitemapStore $store,
        private SearchConsoleClient $searchConsole,
    ) {}

    public function rebuild(string $group, string $trigger): void
    {
        $selected = SitemapGroup::tryFrom($group);
        if (! $selected instanceof SitemapGroup) {
            throw new InvalidArgumentException('Unknown sitemap group.');
        }

        $this->locked(function () use ($selected, $trigger): void {
            $started = now();
            try {
                [$urls, $images] = $this->writeGroup($selected);
                $this->store->publishIndex($this->xml);
                $this->store->markSuccess();
                $this->submitIndex();
                $this->record($selected->value, $trigger, 'success', null, $urls, $images, $started);
            } catch (Throwable $exception) {
                $this->store->markFailure($exception->getMessage());
                $this->record($selected->value, $trigger, 'failed', $exception->getMessage(), 0, 0, $started);
                throw $exception;
            }
        });
    }

    public function rebuildAll(string $trigger): void
    {
        $this->locked(function () use ($trigger): void {
            $started = now();
            try {
                $rendered = [];
                foreach (SitemapGroup::cases() as $group) {
                    $snapshot = $this->catalog->collect($group);
                    $rendered[$group->value] = [
                        'group' => $group,
                        'files' => $this->xml->render($group, $snapshot->entries),
                        'exclusions' => $snapshot->exclusions,
                        'urls' => $group === SitemapGroup::Images ? count($snapshot->entries) : count($snapshot->entries),
                        'images' => array_sum(array_map(fn (SitemapEntry $entry): int => count($entry->images), $snapshot->entries)),
                    ];
                }

                $urls = 0;
                $images = 0;
                foreach ($rendered as $payload) {
                    $this->store->publish($payload['group'], $payload['files']);
                    $this->replaceExclusions($payload['group']->value, $payload['exclusions']);
                    if ($payload['group'] !== SitemapGroup::Images) {
                        $urls += $payload['urls'];
                    }
                    $images += $payload['images'];
                }

                $this->store->publishIndex($this->xml);
                $this->store->markSuccess();
                $this->submitIndex();
                $this->record(null, $trigger, 'success', null, $urls, $images, $started);
            } catch (Throwable $exception) {
                $this->store->markFailure($exception->getMessage());
                $this->record(null, $trigger, 'failed', $exception->getMessage(), 0, 0, $started);
                throw $exception;
            }
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function writeGroup(SitemapGroup $group): array
    {
        $snapshot = $this->catalog->collect($group);
        $files = $this->xml->render($group, $snapshot->entries);
        $this->store->publish($group, $files);
        $this->replaceExclusions($group->value, $snapshot->exclusions);
        $images = array_sum(array_map(fn (SitemapEntry $entry): int => count($entry->images), $snapshot->entries));

        return [count($snapshot->entries), $images];
    }

    /**
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    private function replaceExclusions(string $group, array $exclusions): void
    {
        $recordedAt = now();
        $rows = [];
        foreach (array_slice($exclusions, 0, 200) as $exclusion) {
            $rows[] = [
                'sitemap_group' => $group,
                'loc' => mb_substr($exclusion['loc'], 0, 2048),
                'reason' => mb_substr($exclusion['reason'], 0, 255),
                'recorded_at' => $recordedAt,
            ];
        }

        DB::transaction(function () use ($group, $rows): void {
            SitemapExclusion::query()->where('sitemap_group', $group)->delete();
            if ($rows !== []) {
                SitemapExclusion::query()->insert($rows);
            }
        });

        $serious = array_values(array_filter(
            $exclusions,
            fn (array $exclusion): bool => ! str_contains($exclusion['reason'], 'noindex'),
        ));
        if ($serious !== []) {
            Log::warning('Sitemap left out addresses that need review.', [
                'group' => $group,
                'count' => count($serious),
            ]);
        }
    }

    private function submitIndex(): void
    {
        $manifest = $this->store->manifest();
        $hash = $manifest['index_sha256'] ?? null;
        if (! is_string($hash) || $hash === '') {
            return;
        }
        if ($hash === ($manifest['search_console']['index_sha256'] ?? null)) {
            return;
        }

        $message = $this->searchConsole->submit(SitemapUrl::page('/sitemap.xml'));
        if ($message === null) {
            return;
        }

        $this->store->updateManifest(function (array $manifest) use ($message, $hash): array {
            $manifest['search_console'] = [
                'submitted_at' => $message === '' ? now()->toAtomString() : ($manifest['search_console']['submitted_at'] ?? null),
                'message' => $message === '' ? 'Submitted to Search Console.' : $message,
                'index_sha256' => $message === '' ? $hash : ($manifest['search_console']['index_sha256'] ?? null),
            ];

            return $manifest;
        });
    }

    private function record(
        ?string $group,
        string $trigger,
        string $status,
        ?string $message,
        int $urls,
        int $images,
        mixed $started,
    ): void {
        try {
            SitemapRun::query()->create([
                'sitemap_group' => $group,
                'trigger' => $trigger,
                'status' => $status,
                'url_count' => $urls,
                'image_count' => $images,
                'message' => $message === null ? null : mb_substr($message, 0, 2000),
                'started_at' => $started,
                'finished_at' => now(),
            ]);
            $keep = SitemapRun::query()->orderByDesc('id')->limit(100)->pluck('id');
            if ($keep->count() === 100) {
                SitemapRun::query()->whereNotIn('id', $keep)->delete();
            }
        } catch (Throwable $exception) {
            Log::error('Sitemap history could not be saved.', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * @param  callable(): void  $callback
     */
    private function locked(callable $callback): void
    {
        $lock = Cache::lock('sitemap-publish', 90);
        try {
            $lock->block(30);
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException('Sitemap generation is already running.', 0, $exception);
        }

        try {
            $callback();
        } finally {
            $lock->release();
        }
    }
}
