<?php

namespace App\Console\Commands;

use App\Services\CatalogueIndex;
use App\Support\ResponsivePicture;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('images:variants {--path=* : Only these public image paths}')]
#[Description('Write AVIF and WebP variants for public photographs')]
class GenerateImageVariants extends Command
{
    public function handle(ResponsivePicture $pictures): int
    {
        if (! function_exists('imageavif') || ! function_exists('imagewebp')) {
            $this->error('This PHP build cannot encode AVIF and WebP.');

            return self::FAILURE;
        }

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $requested = array_values(array_filter($this->option('path')));
        $paths = $requested === [] ? $pictures->discover() : $requested;
        $encoded = 0;
        $bar = $this->output->createProgressBar(count($paths));
        $bar->start();

        foreach ($paths as $path) {
            try {
                if ($pictures->ensure((string) $path)) {
                    $encoded++;
                }
            } catch (\Throwable $exception) {
                $this->newLine();
                $this->warn($path.': '.$exception->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info($encoded.' photographs have AVIF and WebP variants.');

        if ($requested === []) {
            CatalogueIndex::write();
            $this->info('Catalogue index updated.');
        }

        return self::SUCCESS;
    }
}
