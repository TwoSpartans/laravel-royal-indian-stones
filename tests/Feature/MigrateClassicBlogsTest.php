<?php

namespace Tests\Feature;

use App\Models\JournalMedia;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MigrateClassicBlogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_keeps_headings_lists_quotes_and_inline_images(): void
    {
        $admin = User::factory()->create();
        $directory = storage_path('app/public/journal/classic-yard');
        File::ensureDirectoryExists($directory);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        File::put($directory.'/inline.png', $png);
        File::put($directory.'/one.png', $png);
        File::put($directory.'/two.png', $png);
        File::put($directory.'/cover.png', $png);

        $html = <<<'HTML'
<h2>What to look for</h2>
<p>The yard keeps <strong>sandstone</strong> dry.</p>
<ul><li>Check the grain.</li><li>Avoid standing water.</li></ul>
<blockquote>Stone remembers the quarry.</blockquote>
<img src="storage/journal/classic-yard/one.png" alt="First slab">
<img src="storage/journal/classic-yard/two.png" alt="Second slab">
<p>A single photograph follows the pair.</p>
<p><img src="storage/journal/classic-yard/inline.png" alt="Stacked sandstone"></p>
<img src="https://example.test/missing.jpg" alt="Missing">
HTML;

        $post = Post::query()->create([
            'slug' => 'classic-yard-note',
            'title' => 'Classic yard note',
            'summary' => 'A classic article that is long enough to publish later on from the Jodhpur yard, with room to describe the colour and the finish.',
            'body' => $html,
            'image' => 'storage/journal/classic-yard/cover.png',
            'featured_image_alt' => 'Cover of stacked sandstone',
            'content_source' => 'classic',
            'status' => 'published',
            'is_published' => true,
            'published_on' => now()->toDateString(),
            'lock_version' => 1,
        ]);

        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, $this->artisan('blogs:migrate-classic', ['post' => $post->id, '--dry-run' => true]));
        $this->assertStringContainsString('Would migrate', Artisan::output());

        $post->refresh();
        $this->assertSame('classic', $post->content_source);
        $this->assertNull($post->classic_body_backup);
        $this->assertSame(0, JournalMedia::query()->count());

        $this->assertSame(0, $this->artisan('blogs:migrate-classic', ['post' => $post->id]));
        $migratedOutput = Artisan::output();
        $this->assertStringContainsString('Migrated', $migratedOutput);
        $this->assertStringContainsString('left behind', $migratedOutput);

        $post->refresh();
        $this->assertSame('studio', $post->content_source);
        $this->assertSame($html, $post->classic_body_backup);
        $this->assertSame($html, $post->body);
        $this->assertSame(2, $post->lock_version);
        $this->assertNotNull(JournalMedia::query()->where('path', 'storage/journal/classic-yard/cover.png')->first());

        $types = array_column($post->body_document['content'], 'type');
        $this->assertContains('heading', $types);
        $this->assertContains('gallery', $types);
        $this->assertContains('image', $types);
        $heading = collect($post->body_document['content'])->firstWhere('type', 'heading');
        $this->assertSame('What to look for', $heading['content'][0]['text']);
        $bold = collect($post->body_document['content'])->contains(function (array $block): bool {
            foreach ($block['content'] ?? [] as $child) {
                if (is_array($child) && (($child['marks'][0]['type'] ?? '') === 'bold')) {
                    return true;
                }
            }

            return false;
        });
        $this->assertTrue($bold);
        $gallery = collect($post->body_document['content'])->firstWhere('type', 'gallery');
        $this->assertCount(2, $gallery['attrs']['items']);
        $this->assertSame('First slab', $gallery['attrs']['items'][0]['alt']);

        $this->get('/classic-yard-note')
            ->assertOk()
            ->assertSee('What to look for')
            ->assertSee('Check the grain.')
            ->assertSee('Stone remembers the quarry.')
            ->assertSee('Stacked sandstone')
            ->assertSee('Cover of stacked sandstone');

        $this->assertSame(0, $this->artisan('blogs:migrate-classic', ['post' => $post->id]));
        $this->assertStringContainsString('Skipped', Artisan::output());
        $this->assertSame(2, $post->refresh()->lock_version);

        $this->actingAs($admin)->get('/admin/posts/'.$post->id.'/edit')
            ->assertRedirect('/admin/posts/'.$post->id.'/studio');
        $this->actingAs($admin)->get('/admin/posts/'.$post->id.'/studio')
            ->assertOk()
            ->assertSee('What to look for')
            ->assertDontSee('Classic editor article');
    }

    public function test_opening_a_classic_article_migrates_it_into_studio(): void
    {
        $admin = User::factory()->create();
        $post = Post::query()->create([
            'slug' => 'opened-classic',
            'title' => 'Opened classic',
            'summary' => '',
            'body' => '<p>The crate is labelled.</p><h3>In the yard</h3>',
            'content_source' => 'classic',
            'status' => 'draft',
            'is_published' => false,
            'lock_version' => 1,
        ]);

        $this->actingAs($admin)->get('/admin/posts/'.$post->id.'/studio')->assertOk()->assertSee('The crate is labelled.');

        $post->refresh();
        $this->assertSame('studio', $post->content_source);
        $this->assertSame('<p>The crate is labelled.</p><h3>In the yard</h3>', $post->classic_body_backup);
        $this->assertSame(3, collect($post->body_document['content'])->firstWhere('type', 'heading')['attrs']['level']);
    }

    public function test_a_failed_schedule_stores_the_reason_and_can_be_published_after_the_fix(): void
    {
        $admin = User::factory()->create();
        $post = Post::query()->create([
            'slug' => 'missed-schedule',
            'title' => 'Missed schedule',
            'summary' => 'Too short',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'scheduled',
            'is_published' => false,
            'scheduled_for' => now()->subMinute(),
            'lock_version' => 1,
            'body_document' => [
                'type' => 'doc',
                'schemaVersion' => 1,
                'content' => [
                    ['type' => 'paragraph', 'attrs' => ['blockId' => 'paragraph01'], 'content' => [['type' => 'text', 'text' => 'The stone waited.']]],
                ],
            ],
        ]);

        $this->artisan('blogs:publish-scheduled')->assertSuccessful();
        $post->refresh();
        $this->assertSame('schedule_failed', $post->status);
        $this->assertStringContainsString('160', (string) $post->schedule_failure_reason);
        $this->get('/missed-schedule')->assertNotFound();

        $excerpt = 'Sandstone, limestone, marble and granite notes from the Jodhpur yard, written so a buyer can judge the colour, the finish, and how the surface will wear outdoors.';
        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => $post->lock_version,
            'metadata' => ['title' => 'Missed schedule', 'excerpt' => $excerpt],
            'document' => $post->body_document,
        ])->assertOk();

        $this->actingAs($admin)->postJson('/admin/posts/'.$post->id.'/studio/publish')->assertOk();
        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertNull($post->schedule_failure_reason);
        $this->get('/missed-schedule')->assertOk()->assertSee('The stone waited.');
    }

    public function test_deleting_an_article_removes_its_library_photograph(): void
    {
        $admin = User::factory()->create();
        $directory = storage_path('app/public/journal/delete-yard');
        File::ensureDirectoryExists($directory);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        File::put($directory.'/photo.png', $png);
        $media = JournalMedia::query()->create([
            'path' => 'storage/journal/delete-yard/photo.png',
            'alt' => 'A slab',
        ]);
        $post = Post::query()->create([
            'slug' => 'delete-yard-note',
            'title' => 'Delete yard note',
            'summary' => '',
            'body' => '<img src="storage/journal/delete-yard/photo.png" alt="A slab">',
            'image' => 'storage/journal/delete-yard/photo.png',
            'content_source' => 'studio',
            'status' => 'draft',
            'is_published' => false,
        ]);
        PostReference::query()->create([
            'post_id' => $post->id,
            'kind' => 'media',
            'reference_id' => $media->id,
            'block_id' => 'imageblock01',
        ]);

        $this->actingAs($admin)->delete('/admin/posts/'.$post->id)->assertRedirect(route('admin.posts.index'));

        $this->assertNull(JournalMedia::query()->find($media->id));
        $this->assertFileDoesNotExist($directory.'/photo.png');
    }

    public function test_an_untitled_draft_takes_its_slug_from_the_title_and_avoids_a_collision(): void
    {
        $admin = User::factory()->create();
        Post::query()->create([
            'slug' => 'mint-sandstone-floor',
            'title' => 'Already used',
            'summary' => '',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'draft',
            'is_published' => false,
        ]);
        $post = Post::query()->create([
            'slug' => 'untitled-article',
            'title' => 'Untitled article',
            'summary' => '',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'draft',
            'is_published' => false,
            'lock_version' => 1,
            'body_document' => [
                'type' => 'doc',
                'schemaVersion' => 1,
                'content' => [
                    ['type' => 'paragraph', 'content' => []],
                ],
            ],
        ]);

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'metadata' => ['title' => 'Mint sandstone floor'],
            'document' => $post->body_document,
        ])->assertOk()->assertJsonPath('slug', 'mint-sandstone-floor-2');

        $this->assertSame('mint-sandstone-floor-2', $post->refresh()->slug);
    }

    public function test_category_ids_store_only_the_first_selected_category(): void
    {
        $admin = User::factory()->create();
        $first = PostCategory::query()->create(['name' => 'Floors', 'slug' => 'floors']);
        $second = PostCategory::query()->create(['name' => 'Walls', 'slug' => 'walls']);
        $post = Post::query()->create([
            'slug' => 'category-note',
            'title' => 'Category note',
            'summary' => '',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'draft',
            'is_published' => false,
            'lock_version' => 1,
            'body_document' => [
                'type' => 'doc',
                'schemaVersion' => 1,
                'content' => [
                    ['type' => 'paragraph', 'content' => []],
                ],
            ],
        ]);

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'metadata' => [
                'title' => 'Category note',
                'categoryIds' => [$second->id, $first->id],
            ],
            'document' => $post->body_document,
        ])->assertOk();

        $this->assertSame($second->id, $post->refresh()->post_category_id);
    }
}
