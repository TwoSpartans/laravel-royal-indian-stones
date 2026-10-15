<?php

namespace App\Console\Commands;

use App\Services\CatalogueImporter;
use Illuminate\Console\Command;

class ImportCatalogue extends Command
{
    protected $signature = 'catalogue:import {--source=}';

    protected $description = 'Import stone products from the existing Royal Indian Stones HTML site';

    public function handle(CatalogueImporter $importer): int
    {
        $source = $this->option('source') ?: (string) config('catalogue.source');
        $count = $importer->import($source);
        $this->info("Imported {$count} products from {$source}.");

        return self::SUCCESS;
    }
}
