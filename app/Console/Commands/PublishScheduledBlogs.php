<?php

namespace App\Console\Commands;

use App\Services\BlogStudio\StudioDocumentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('blogs:publish-scheduled')]
#[Description('Publish studio articles whose scheduled time has arrived')]
class PublishScheduledBlogs extends Command
{
    public function handle(StudioDocumentService $studio): int
    {
        $count = $studio->publishDue();
        $this->info($count === 1 ? 'Published 1 article.' : 'Published '.$count.' articles.');

        return self::SUCCESS;
    }
}
