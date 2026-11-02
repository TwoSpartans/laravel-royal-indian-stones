<?php

namespace App\Services\Sitemap;

use App\Enums\SitemapGroup;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class SitemapStore
{
    public function directory(): string
    {
        return storage_path('app/sitemaps');
    }

    public function allowed(string $name): bool
    {
        return preg_match('/^sitemap(?:-(?:categories|products|images|pages|journal)(?:-(?:[2-9]|[1-9][0-9]+))?)?\.xml$/', $name) === 1;
    }

    public function exists(string $name): bool
    {
        return $this->allowed($name) && is_file($this->path($name));
    }

    public function response(string $name): Response
    {
        $contents = file_get_contents($this->path($name));

        return response($contents === false ? '' : $contents, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * @param  list<RenderedSitemap>  $files
     */
    public function publish(SitemapGroup $group, array $files): void
    {
        File::ensureDirectoryExists($this->directory());
        $manifest = $this->manifest();
        $previous = [];
        foreach ($manifest['groups'][$group->value]['files'] ?? [] as $file) {
            if (is_array($file) && isset($file['name'])) {
                $previous[(string) $file['name']] = $file;
            }
        }

        $published = [];
        foreach ($files as $file) {
            $hash = hash('sha256', $file->xml);
            $current = $this->path($file->name);
            if (! is_file($current) || ! hash_equals($hash, (string) hash_file('sha256', $current))) {
                $this->writeAtomic($file->name, $file->xml);
            }

            $published[] = [
                'name' => $file->name,
                'urls' => $file->urls,
                'images' => $file->images,
                'bytes' => strlen($file->xml),
                'sha256' => $hash,
                'lastmod' => $previous[$file->name]['lastmod'] ?? $file->lastmod?->toAtomString(),
            ];
            if (! is_file($current) || ! hash_equals($hash, (string) ($previous[$file->name]['sha256'] ?? ''))) {
                $published[array_key_last($published)]['lastmod'] = $file->lastmod?->toAtomString();
            }
        }

        $this->deleteStale($group, array_column($published, 'name'));
        $this->updateManifest(function (array $manifest) use ($group, $published): array {
            if ($published === []) {
                unset($manifest['groups'][$group->value]);

                return $manifest;
            }

            $manifest['groups'][$group->value] = [
                'urls' => array_sum(array_column($published, 'urls')),
                'images' => array_sum(array_column($published, 'images')),
                'files' => $published,
            ];

            return $manifest;
        });
    }

    public function publishIndex(SitemapXml $xml): void
    {
        $rows = [];
        foreach (SitemapGroup::cases() as $group) {
            foreach ($this->manifest()['groups'][$group->value]['files'] ?? [] as $file) {
                if (! is_array($file) || ! isset($file['name'])) {
                    continue;
                }
                $rows[] = [
                    'loc' => SitemapUrl::page('/'.$file['name']),
                    'lastmod' => is_string($file['lastmod'] ?? null) ? $file['lastmod'] : null,
                ];
            }
        }

        $document = $xml->index($rows);
        $hash = hash('sha256', $document);
        $current = $this->path('sitemap.xml');
        if (is_file($current) && hash_equals($hash, (string) hash_file('sha256', $current))) {
            $this->updateManifest(function (array $manifest) use ($hash): array {
                $manifest['index_sha256'] = $hash;

                return $manifest;
            });

            return;
        }

        $this->writeAtomic('sitemap.xml', $document);
        $this->updateManifest(function (array $manifest) use ($hash): array {
            $manifest['index_sha256'] = $hash;

            return $manifest;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $path = $this->path('manifest.json');
        $blank = $this->blankManifest();
        if (! is_file($path)) {
            return $blank;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return $blank;
        }

        $decoded['groups'] = is_array($decoded['groups'] ?? null) ? $decoded['groups'] : [];
        $decoded['validation'] = array_merge($blank['validation'], is_array($decoded['validation'] ?? null) ? $decoded['validation'] : []);
        $decoded['search_console'] = array_merge($blank['search_console'], is_array($decoded['search_console'] ?? null) ? $decoded['search_console'] : []);

        return array_merge($blank, $decoded);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     */
    public function updateManifest(callable $callback): void
    {
        $updated = $callback($this->manifest());
        $this->writeAtomic(
            'manifest.json',
            json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array{categories: int, products: int, pages: int, journal: int, images: int, total: int}
     */
    public function counts(): array
    {
        $manifest = $this->manifest();
        $counts = [
            'categories' => (int) ($manifest['groups']['categories']['urls'] ?? 0),
            'products' => (int) ($manifest['groups']['products']['urls'] ?? 0),
            'pages' => (int) ($manifest['groups']['pages']['urls'] ?? 0),
            'journal' => (int) ($manifest['groups']['journal']['urls'] ?? 0),
            'images' => (int) ($manifest['groups']['images']['images'] ?? 0),
            'total' => 0,
        ];
        $counts['total'] = $counts['categories'] + $counts['products'] + $counts['pages'] + $counts['journal'];

        return $counts;
    }

    public function status(): string
    {
        $manifest = $this->manifest();
        $errorAt = strtotime((string) ($manifest['last_error_at'] ?? ''));
        $successAt = strtotime((string) ($manifest['last_success_at'] ?? ''));
        if ($errorAt !== false && ($successAt === false || $errorAt > $successAt)) {
            return 'error';
        }
        if (($manifest['validation']['errors'] ?? []) !== []) {
            return 'error';
        }
        if (($manifest['last_success_at'] ?? null) === null) {
            return 'warning';
        }
        if (($manifest['validation']['warnings'] ?? []) !== []) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function saveValidation(array $errors, array $warnings): void
    {
        $this->updateManifest(function (array $manifest) use ($errors, $warnings): array {
            $manifest['validation'] = [
                'checked_at' => now()->toAtomString(),
                'errors' => $errors,
                'warnings' => $warnings,
            ];

            return $manifest;
        });
    }

    public function markSuccess(): void
    {
        $this->updateManifest(function (array $manifest): array {
            $manifest['last_success_at'] = now()->toAtomString();
            $manifest['last_error'] = null;

            return $manifest;
        });
    }

    public function markFailure(string $message): void
    {
        $this->updateManifest(function (array $manifest) use ($message): array {
            $manifest['last_error_at'] = now()->toAtomString();
            $manifest['last_error'] = mb_substr($message, 0, 2000);

            return $manifest;
        });
    }

    /**
     * @param  list<string>  $keep
     */
    private function deleteStale(SitemapGroup $group, array $keep): void
    {
        $keep = array_flip($keep);
        $pattern = '/^sitemap-'.preg_quote($group->value, '/').'(?:-[0-9]+)?\.xml$/';
        foreach (glob($this->directory().DIRECTORY_SEPARATOR.'sitemap-'.$group->value.'*.xml') ?: [] as $path) {
            $name = basename($path);
            if (! isset($keep[$name]) && preg_match($pattern, $name) === 1) {
                unlink($path);
            }
        }
    }

    private function writeAtomic(string $name, string $contents): void
    {
        File::ensureDirectoryExists($this->directory());
        $temporary = $this->path($name.'.tmp');
        $target = $this->path($name);
        $backup = $this->path($name.'.bak');
        if (file_put_contents($temporary, $contents) === false) {
            throw new RuntimeException('Could not write '.$name.'.');
        }

        if (is_file($backup)) {
            unlink($backup);
        }

        $preserved = false;
        if (is_file($target)) {
            if (! rename($target, $backup)) {
                unlink($temporary);
                throw new RuntimeException('Could not preserve '.$name.'.');
            }
            $preserved = true;
        }

        if (! rename($temporary, $target)) {
            if ($preserved && is_file($backup)) {
                rename($backup, $target);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw new RuntimeException('Could not publish '.$name.'.');
        }

        if ($preserved && is_file($backup)) {
            unlink($backup);
        }
    }

    private function path(string $name): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.basename($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankManifest(): array
    {
        return [
            'last_success_at' => null,
            'last_error_at' => null,
            'last_error' => null,
            'index_sha256' => null,
            'groups' => [],
            'validation' => [
                'checked_at' => null,
                'errors' => [],
                'warnings' => [],
            ],
            'search_console' => [
                'submitted_at' => null,
                'message' => null,
                'index_sha256' => null,
            ],
        ];
    }
}
