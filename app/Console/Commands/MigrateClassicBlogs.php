<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\BlogStudio\StudioDocumentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Signature('blogs:migrate-classic {post? : Article id} {--dry-run : Report the result without saving} {--force : Rebuild a Studio article from its classic HTML backup}')]
#[Description('Turn classic blog articles into Blog Studio documents')]
class MigrateClassicBlogs extends Command
{
    public function handle(StudioDocumentService $studio): int
    {
        $id = $this->argument('post');
        $hasId = $id !== null && $id !== '';
        $query = Post::query()->orderBy('id');
        if ($hasId) {
            $query->whereKey((int) $id);
        } elseif (! $this->option('force')) {
            $query->where(function ($query): void {
                $query->where('content_source', 'classic')->orWhereNull('content_source');
            });
        }

        $posts = $query->get();
        if ($posts->isEmpty()) {
            $this->warn($hasId ? 'No article with that id.' : 'No classic articles to migrate.');

            return $hasId ? self::FAILURE : self::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($posts as $post) {
            try {
                $summary = $studio->migrateClassic($post, (bool) $this->option('dry-run'), (bool) $this->option('force'));
            } catch (ValidationException $exception) {
                $failed++;
                $this->error('#'.$post->id.' '.$post->title.' could not be migrated: '.implode(' ', $exception->validator->errors()->all()));

                continue;
            } catch (Throwable $exception) {
                $failed++;
                $this->error('#'.$post->id.' '.$post->title.' could not be migrated: '.$exception->getMessage());

                continue;
            }

            $line = '#'.$summary['id'].' '.$summary['title'].' — '.$summary['blocks'].' blocks, '.$summary['images'].' photographs';
            if ($summary['missingImages'] > 0) {
                $line .= ', '.$summary['missingImages'].($summary['missingImages'] === 1 ? ' photograph' : ' photographs').' left behind because the file was not on this site';
            }
            if ($summary['action'] === 'skipped') {
                $skipped++;
                $this->line('Skipped '.$line);
            } elseif ($summary['action'] === 'dry-run') {
                $migrated++;
                $this->info('Would migrate '.$line);
            } else {
                $migrated++;
                $this->info('Migrated '.$line);
            }
        }

        $this->newLine();
        $verb = $this->option('dry-run') ? 'Would migrate' : 'Migrated';
        $this->info($verb.' '.$migrated.'. Skipped '.$skipped.'. Failed '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
