<?php

namespace App\Services\BlogStudio;

use RuntimeException;

/**
 * Rolls a dry-run migration back out of the surrounding database transaction.
 */
final class ClassicMigrationDryRun extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(public array $summary)
    {
        parent::__construct('Classic migration dry run.');
    }
}
