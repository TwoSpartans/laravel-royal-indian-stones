<?php

namespace App\Console\Commands;

use App\Services\ContentImporter;
use App\Support\AdminAccount;
use Illuminate\Console\Command;

class ImportContent extends Command
{
    protected $signature = 'content:import {--source=}';

    protected $description = 'Import company pages, gallery, FAQ, and journal articles from the existing HTML site';

    public function handle(ContentImporter $importer): int
    {
        $source = $this->option('source') ?: (string) config('catalogue.source');
        $counts = $importer->import($source);
        $this->info("Imported {$counts['pages']} pages, {$counts['projects']} projects, {$counts['faqs']} FAQs, {$counts['posts']} journal articles, and {$counts['introductions']} category introductions from {$source}.");
        if (AdminAccount::sync()) {
            $this->info('Admin account is ready.');
        } else {
            $this->warn('Set ADMIN_EMAIL and ADMIN_PASSWORD to create the admin account.');
        }

        return self::SUCCESS;
    }
}
