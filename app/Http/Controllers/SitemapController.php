<?php

namespace App\Http\Controllers;

use App\Services\Sitemap\SitemapPublisher;
use App\Services\Sitemap\SitemapStore;
use Illuminate\Http\Response;
use Throwable;

class SitemapController extends Controller
{
    public function index(SitemapStore $store, SitemapPublisher $publisher): Response
    {
        return $this->file('sitemap.xml', $store, $publisher);
    }

    public function show(string $name, SitemapStore $store, SitemapPublisher $publisher): Response
    {
        return $this->file('sitemap-'.$name.'.xml', $store, $publisher);
    }

    private function file(string $name, SitemapStore $store, SitemapPublisher $publisher): Response
    {
        if (! $store->allowed($name)) {
            abort(404);
        }

        if (! $store->exists($name) && ! $store->exists('sitemap.xml')) {
            try {
                $publisher->rebuildAll('request');
            } catch (Throwable $exception) {
                report($exception);
                if (! $store->exists($name)) {
                    abort(503);
                }
            }
        }

        if (! $store->exists($name)) {
            abort(404);
        }

        return $store->response($name);
    }
}
