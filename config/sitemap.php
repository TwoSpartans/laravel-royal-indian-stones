<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sitemap limits
    |--------------------------------------------------------------------------
    |
    | A sitemap file splits when it reaches either limit. Google allows
    | 1,000 images under one page address.
    |
    */

    'max_urls' => 50000,

    'max_bytes' => 50 * 1024 * 1024,

    'max_images_per_url' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Event debounce
    |--------------------------------------------------------------------------
    |
    | Queued rebuilds wait this many seconds so a burst of edits becomes
    | one rebuild. The sync queue used by tests runs the job immediately.
    |
    */

    'debounce_seconds' => 15,

    /*
    |--------------------------------------------------------------------------
    | Image host
    |--------------------------------------------------------------------------
    |
    | Leave empty to publish image addresses on APP_URL. Set a CDN origin
    | only when that host serves the same files and is verified for the
    | site in Google Search Console.
    |
    */

    'asset_url' => env('SITEMAP_ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Public pages
    |--------------------------------------------------------------------------
    |
    | Routed pages are registered before the catch-all slug. Resolved pages
    | are chosen inside that catch-all, after a product or category with the
    | same slug. Categories are one level deep; each public category uses
    | the slug the site already serves.
    |
    */

    'routed_pages' => [
        '/' => 'Home',
        '/products' => 'Shop',
        '/e-catalogue' => 'E-catalogue',
        '/contact' => 'Contact',
    ],

    'resolved_pages' => [
        '/natural-stones-of-india' => 'Stone categories',
        '/aboutus' => 'About',
        '/project-gallery' => 'Project gallery',
        '/faq' => 'FAQ',
        '/blog' => 'Journal',
        '/sitemap' => 'Sitemap',
    ],

    'blocked_slugs' => [
        'blogs',
        'index',
        'about',
        'admin',
        'portal',
        'up',
        'storage',
        'build',
        'assets',
        'vendor',
        'api',
        'livewire',
    ],

    'private_prefixes' => [
        '/admin',
        '/portal',
        '/up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Console
    |--------------------------------------------------------------------------
    |
    | When a site URL and access token are set, a changed sitemap index is
    | submitted to Search Console. Submission does not request indexing.
    |
    */

    'search_console' => [
        'enabled' => (bool) env('SITEMAP_SEARCH_CONSOLE_ENABLED', false),
        'site_url' => env('SITEMAP_SEARCH_CONSOLE_SITE'),
        'access_token' => env('SITEMAP_SEARCH_CONSOLE_TOKEN'),
    ],

];
