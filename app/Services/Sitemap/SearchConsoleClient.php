<?php

namespace App\Services\Sitemap;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SearchConsoleClient
{
    /**
     * Submit the sitemap index when Search Console credentials are configured.
     * A null return means submission is off. An empty string means it was accepted.
     */
    public function submit(string $sitemapUrl): ?string
    {
        if (! config('sitemap.search_console.enabled')) {
            return null;
        }

        $token = trim((string) config('sitemap.search_console.access_token'));
        $site = trim((string) config('sitemap.search_console.site_url'));
        if ($token === '' || $site === '') {
            Log::warning('Search Console sitemap submission is enabled without a site or access token.');

            return 'Search Console submission is enabled without a site or access token.';
        }

        $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
            .rawurlencode($site).'/sitemaps/'.rawurlencode($sitemapUrl);

        try {
            $response = Http::withToken($token)->timeout(10)->acceptJson()->put($endpoint);
        } catch (ConnectionException $exception) {
            Log::warning('Search Console sitemap submission failed.', ['message' => $exception->getMessage()]);

            return 'Search Console could not be reached.';
        } catch (Throwable $exception) {
            Log::warning('Search Console sitemap submission failed.', ['message' => $exception->getMessage()]);

            return 'Search Console could not be reached.';
        }

        if ($response->failed()) {
            Log::warning('Search Console rejected the sitemap submission.', ['status' => $response->status()]);

            return 'Search Console returned HTTP '.$response->status().'.';
        }

        return '';
    }
}
