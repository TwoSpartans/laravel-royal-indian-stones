<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final class ServedUpload
{
    public function store(UploadedFile $photo, string $directory, string $filename): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $filename = trim(str_replace('\\', '/', $filename), '/');
        if ($directory === '' || $filename === '' || str_contains($directory, '..') || str_contains($filename, '/')) {
            throw new InvalidArgumentException('Invalid upload path.');
        }

        $absoluteDirectory = storage_path('app/public/'.$directory);
        File::ensureDirectoryExists($absoluteDirectory);
        $photo->move($absoluteDirectory, $filename);
        $absolute = $absoluteDirectory.DIRECTORY_SEPARATOR.$filename;
        @chmod($absoluteDirectory, 0755);
        @chmod($absolute, 0644);

        return 'storage/'.$directory.'/'.$filename;
    }

    public function absolute(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            return storage_path('app/public/'.substr($path, strlen('storage/')));
        }

        if (str_starts_with($path, 'assets/')) {
            return public_path($path);
        }

        return null;
    }

    public function delete(string $path): void
    {
        if (! $this->isManaged($path)) {
            return;
        }

        $absolute = $this->absolute($path);
        if ($absolute !== null && is_file($absolute)) {
            unlink($absolute);
        }
    }

    /**
     * Copy a photograph saved in the application folder into the directory the live site serves.
     */
    public function relocate(string $path, string $fromPrefix, string $directory): ?string
    {
        $path = str_replace('\\', '/', $path);
        $fromPrefix = trim(str_replace('\\', '/', $fromPrefix), '/').'/';
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, $fromPrefix)) {
            return null;
        }

        $source = public_path($path);
        if (! is_file($source)) {
            return null;
        }

        $remainder = substr($path, strlen($fromPrefix));
        $relativeDirectory = trim($directory.'/'.dirname($remainder), '/');
        if ($relativeDirectory === '.' || str_contains($relativeDirectory, '..')) {
            $relativeDirectory = trim($directory, '/');
        }

        $filename = basename($remainder);
        $destinationDirectory = storage_path('app/public/'.$relativeDirectory);
        File::ensureDirectoryExists($destinationDirectory);
        $destination = $destinationDirectory.DIRECTORY_SEPARATOR.$filename;
        if (! is_file($destination)) {
            File::copy($source, $destination);
        }
        @chmod($destinationDirectory, 0755);
        @chmod($destination, 0644);

        return 'storage/'.$relativeDirectory.'/'.$filename;
    }

    private function isManaged(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        foreach ([
            'storage/home/',
            'storage/products/',
            'storage/journal/',
            'assets/img/home/',
            'assets/img/products/',
            'assets/img/journal/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
