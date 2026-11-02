<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SitemapGroup;
use App\Http\Controllers\Controller;
use App\Models\SitemapExclusion;
use App\Models\SitemapRun;
use App\Services\Sitemap\SitemapPublisher;
use App\Services\Sitemap\SitemapStore;
use App\Services\Sitemap\SitemapValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SitemapController extends Controller
{
    public function index(SitemapStore $store, SitemapPublisher $publisher): View
    {
        if (! $store->exists('sitemap.xml')) {
            try {
                $publisher->rebuildAll('request');
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('admin.sitemap.index', $this->dashboard($store));
    }

    public function exclusions(): View
    {
        return view('admin.sitemap.exclusions', [
            'exclusions' => SitemapExclusion::query()->orderBy('sitemap_group')->orderBy('loc')->limit(200)->get(),
        ]);
    }

    public function history(): View
    {
        return view('admin.sitemap.history', [
            'runs' => SitemapRun::query()->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function regenerate(SitemapPublisher $publisher): RedirectResponse
    {
        return $this->run(fn () => $publisher->rebuildAll('manual'), 'The sitemap was regenerated.');
    }

    public function regenerateGroup(Request $request, SitemapPublisher $publisher): RedirectResponse
    {
        $group = $request->string('group')->toString();
        if (SitemapGroup::tryFrom($group) === null) {
            abort(404);
        }

        return $this->run(
            fn () => $publisher->rebuild($group, 'manual'),
            SitemapGroup::from($group)->label().' sitemap regenerated.',
        );
    }

    public function validateSitemap(SitemapValidator $validator): RedirectResponse
    {
        $result = $validator->validate();
        if ($result['errors'] !== []) {
            return redirect()
                ->route('admin.sitemap.index')
                ->with('error', 'Validation found '.count($result['errors']).' problem'.(count($result['errors']) === 1 ? '' : 's').'.');
        }

        return redirect()
            ->route('admin.sitemap.index')
            ->with('status', 'Validation found no errors.');
    }

    /**
     * @param  callable(): void  $rebuild
     */
    private function run(callable $rebuild, string $success): RedirectResponse
    {
        try {
            $rebuild();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.sitemap.index')
                ->with('error', 'The rebuild failed. The last successful sitemap files are still public.');
        }

        return redirect()->route('admin.sitemap.index')->with('status', $success);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(SitemapStore $store): array
    {
        $manifest = $store->manifest();
        $exclusionCount = SitemapExclusion::query()->count();
        $status = $store->status();
        if ($status === 'healthy' && $exclusionCount > 0) {
            $status = 'warning';
        }

        $files = [[
            'name' => 'sitemap.xml',
            'label' => 'Index',
            'url' => route('sitemap.xml'),
            'urls' => null,
            'lastmod' => null,
        ]];
        foreach (SitemapGroup::cases() as $group) {
            foreach ($manifest['groups'][$group->value]['files'] ?? [] as $file) {
                if (! is_array($file) || ! isset($file['name'])) {
                    continue;
                }
                $filename = (string) $file['name'];
                $files[] = [
                    'name' => $filename,
                    'label' => $group->label(),
                    'group' => $group->value,
                    'url' => route('sitemap.show', ['name' => substr($filename, strlen('sitemap-'), -strlen('.xml'))]),
                    'urls' => (int) ($file['urls'] ?? 0),
                    'lastmod' => $file['lastmod'] ?? null,
                    'regenerate' => $filename === $group->filename(),
                ];
            }
        }

        return [
            'status' => $status,
            'counts' => $store->counts(),
            'manifest' => $manifest,
            'files' => $files,
            'exclusionCount' => $exclusionCount,
            'runs' => SitemapRun::query()->orderByDesc('id')->limit(8)->get(),
        ];
    }
}
