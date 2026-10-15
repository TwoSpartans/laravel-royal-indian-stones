<?php

namespace App\Support;

class LaterPages
{
    /**
     * Slugs that still use the short placeholder. Phase 2 pages are served from the database.
     *
     * @var array<string, array{title: string, active: string}>
     */
    public const PAGES = [];
}
