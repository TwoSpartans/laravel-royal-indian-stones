<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JournalMedia;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostDraft;
use App\Models\Product;
use App\Models\User;
use App\Services\BlogStudio\ArticleTemplates;
use App\Services\RichContent\DocumentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_templates_are_valid_documents_the_writer_can_start_from(): void
    {
        $admin = User::factory()->create();
        $post = $this->studioPost();

        $this->actingAs($admin)->get('/admin/posts/'.$post->id.'/studio')
            ->assertOk()
            ->assertSee('Buying Guide')
            ->assertSee('What to look for')
            ->assertSee('Workshop Story')
            ->assertSee('Text and image')
            ->assertSee('Care Guide');

        $validator = app(DocumentValidator::class);

        foreach (app(ArticleTemplates::class)->all() as $template) {
            $result = $validator->validate($template['document']);
            $this->assertTrue($result['valid'], $template['id'].' '.implode(' ', $result['errors']));
            $this->assertSame([], $result['errors']);
        }
    }

    public function test_a_document_with_a_bad_link_unknown_block_or_raw_image_url_is_rejected(): void
    {
        $admin = User::factory()->create();
        $post = $this->studioPost();

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'document' => $this->document([
                ['type' => 'paragraph', 'content' => [[
                    'type' => 'text',
                    'text' => 'Read more',
                    'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]],
                ]]],
            ]),
        ])->assertStatus(422);

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'document' => $this->document([['type' => 'widget']]),
        ])->assertStatus(422);

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'document' => $this->document([['type' => 'image', 'attrs' => ['src' => 'https://example.test/stone.jpg']]]),
        ])->assertStatus(422);

        $this->assertSame(1, $post->refresh()->lock_version);
    }

    public function test_a_text_and_image_block_saves_the_heading_paragraphs_and_chosen_side(): void
    {
        $admin = User::factory()->create();
        $media = JournalMedia::query()->create([
            'path' => 'storage/journal/9/slate-face.jpg',
            'alt' => 'Layered slate',
        ]);
        $post = $this->studioPost();

        $saved = $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'document' => $this->document([
                ['type' => 'splitSection', 'attrs' => [
                    'heading' => '1. The Formation of Slate: <script>alert(1)</script>',
                    'body' => "Slate splits into thin sheets.\n\nThe rock cycle keeps the layers.",
                    'mediaId' => $media->id,
                    'alt' => 'Layered slate rock face',
                    'caption' => 'Quarry face',
                    'layout' => 'image-left',
                ]],
                ['type' => 'splitSection', 'attrs' => [
                    'heading' => 'Second section',
                    'body' => 'More stone.',
                    'mediaId' => $media->id,
                    'alt' => 'Another face',
                    'layout' => 'beside',
                ]],
            ]),
        ]);

        $saved->assertOk();
        $post->refresh();
        $first = $post->body_document['content'][0]['attrs'];
        $second = $post->body_document['content'][1]['attrs'];
        $this->assertSame('1. The Formation of Slate: alert(1)', $first['heading']);
        $this->assertSame("Slate splits into thin sheets.\n\nThe rock cycle keeps the layers.", $first['body']);
        $this->assertSame($media->id, $first['mediaId']);
        $this->assertSame('Layered slate rock face', $first['alt']);
        $this->assertSame('Quarry face', $first['caption']);
        $this->assertSame('image-left', $first['layout']);
        $this->assertArrayNotHasKey('src', $first);
        $this->assertSame('image-right', $second['layout']);
        $this->assertStringContainsString('storage/journal/9/slate-face.jpg', (string) $saved->json('document.content.0.attrs.url'));

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 2,
            'document' => $this->document([
                ['type' => 'splitSection', 'attrs' => ['src' => 'https://example.test/slate.jpg', 'heading' => 'Kept']],
            ]),
        ])->assertStatus(422);

        $this->assertSame('image-left', $post->refresh()->body_document['content'][0]['attrs']['layout']);
    }

    public function test_a_text_and_image_block_renders_the_photograph_beside_the_copy(): void
    {
        $media = JournalMedia::query()->create([
            'path' => 'storage/journal/9/slate-face.jpg',
            'alt' => 'Layered slate',
        ]);
        Post::query()->create([
            'slug' => 'slate-formation',
            'title' => 'The formation of slate',
            'summary' => 'How slate splits into sheets, and why the layers stay strong enough for floors and roofs.',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'published',
            'is_published' => true,
            'body_document' => $this->document([
                ['type' => 'splitSection', 'attrs' => [
                    'heading' => '1. The Formation of Slate:',
                    'body' => "Slate splits into thin sheets.\n\nThe rock cycle keeps the layers.",
                    'mediaId' => $media->id,
                    'alt' => 'Layered slate rock face',
                    'caption' => 'Quarry face',
                    'layout' => 'image-left',
                ]],
            ]),
        ]);

        $this->get('/slate-formation')
            ->assertOk()
            ->assertSee('rich-split', false)
            ->assertSee('is-image-left', false)
            ->assertSee('1. The Formation of Slate:')
            ->assertSee('Slate splits into thin sheets.')
            ->assertSee('The rock cycle keeps the layers.')
            ->assertSee('Layered slate rock face')
            ->assertSee('Quarry face')
            ->assertSee('storage/journal/9/slate-face.jpg', false);
    }

    public function test_publish_stays_blocked_until_a_text_and_image_block_has_a_described_photograph(): void
    {
        $admin = User::factory()->create();
        $media = JournalMedia::query()->create([
            'path' => 'storage/journal/9/slate-face.jpg',
            'alt' => 'Layered slate',
        ]);
        $post = $this->studioPost();
        $post->title = 'Slate in the yard';
        $post->summary = $this->readyExcerpt();
        $post->body_document = $this->document([
            ['type' => 'splitSection', 'attrs' => [
                'heading' => 'The formation',
                'body' => 'Slate splits into thin sheets.',
            ]],
        ]);
        $post->save();

        $missingPhoto = $this->actingAs($admin)->postJson('/admin/posts/'.$post->id.'/studio/publish');

        $missingPhoto->assertStatus(422);
        $missingPhoto->assertJsonFragment([
            'explanation' => 'Choose a photograph from the library. A typed web address is not stored.',
        ]);
        $this->assertFalse($post->refresh()->is_published);

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'document' => $this->document([
                ['type' => 'splitSection', 'attrs' => [
                    'heading' => 'The formation',
                    'body' => 'Slate splits into thin sheets.',
                    'mediaId' => $media->id,
                    'alt' => '',
                ]],
            ]),
        ])->assertOk();

        $missingAlt = $this->actingAs($admin)->postJson('/admin/posts/'.$post->id.'/studio/publish');

        $missingAlt->assertStatus(422);
        $missingAlt->assertJsonFragment([
            'explanation' => 'Describe this photograph, or mark it decorative.',
        ]);
        $this->assertFalse($post->refresh()->is_published);
    }

    public function test_a_product_block_renders_the_catalogue_stone_and_its_frame(): void
    {
        $category = Category::query()->create(['name' => 'Sandstone', 'slug' => 'sandstone', 'position' => 0]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Raj Green Sandstone',
            'slug' => 'sandstone-raj-green',
            'finishes' => [],
            'applications' => [],
            'is_published' => true,
        ]);
        Post::query()->create([
            'slug' => 'courtyard-guide',
            'title' => 'A courtyard guide',
            'summary' => 'How to choose sandstone for a courtyard floor and keep the colour even.',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'published',
            'is_published' => true,
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Start with the colour.']]],
                ['type' => 'productSpotlight', 'attrs' => [
                    'productId' => $product->id,
                    'editorial' => ['eyebrow' => 'Sandstone', 'summary' => 'A green sandstone for floors.'],
                    'frame' => ['width' => 'wide'],
                ]],
            ]),
        ]);

        $this->get('/courtyard-guide')
            ->assertOk()
            ->assertSee('Start with the colour.')
            ->assertSee('Raj Green Sandstone')
            ->assertSee('rich-frame-width-wide', false)
            ->assertDontSee('Copied price');
    }

    public function test_a_second_save_with_the_same_version_conflicts_and_a_live_draft_stays_unpublished(): void
    {
        $admin = User::factory()->create();
        $post = Post::query()->create([
            'slug' => 'live-guide',
            'title' => 'Live guide',
            'summary' => 'Sandstone, limestone, marble and granite notes from the Jodhpur yard, written so a buyer can judge the colour, the finish, and how the surface will wear outdoors.',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'published',
            'is_published' => true,
            'lock_version' => 1,
            'featured_image_alt' => 'Sandstone courtyard',
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Live words']]],
            ]),
        ]);

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'metadata' => ['excerpt' => 'Sandstone, limestone, marble and granite notes from the Jodhpur yard, written so a buyer can judge the colour, the finish, and how the surface will wear outdoors.'],
            'document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Draft words']]],
            ]),
        ])->assertOk();

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Older words']]],
            ]),
        ])->assertStatus(409);

        $this->get('/live-guide')->assertOk()->assertSee('Live words')->assertDontSee('Draft words');

        $this->actingAs($admin)->postJson('/admin/posts/'.$post->id.'/studio/publish')->assertOk();
        $this->get('/live-guide')->assertOk()->assertSee('Draft words');
    }

    public function test_a_live_draft_keeps_the_new_photo_and_category_off_the_public_article(): void
    {
        $admin = User::factory()->create();
        $guides = PostCategory::query()->create(['name' => 'Guides', 'slug' => 'guides']);
        $yards = PostCategory::query()->create(['name' => 'Yards', 'slug' => 'yards']);
        $current = JournalMedia::query()->create(['path' => 'journal/current.jpg', 'alt' => 'Current stone']);
        $next = JournalMedia::query()->create(['path' => 'journal/next.jpg', 'alt' => 'Next stone']);
        $post = Post::query()->create([
            'slug' => 'live-stone',
            'title' => 'Live stone',
            'summary' => $this->readyExcerpt(),
            'body' => '',
            'image' => $current->path,
            'featured_image_alt' => 'Sandstone courtyard',
            'post_category_id' => $guides->id,
            'content_source' => 'studio',
            'status' => 'published',
            'is_published' => true,
            'published_on' => now()->toDateString(),
            'lock_version' => 1,
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Live words']]],
            ]),
        ]);

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'metadata' => [
                'title' => 'Live stone revised',
                'excerpt' => $this->readyExcerpt(),
                'featuredMediaId' => $next->id,
                'categoryIds' => [$yards->id],
            ],
            'content' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Draft words']]],
            ]),
        ])->assertOk();

        $post->refresh();
        $this->assertSame($current->path, $post->image);
        $this->assertSame($guides->id, $post->post_category_id);
        $this->assertSame($next->path, $post->draft->image);
        $this->assertSame($yards->id, $post->draft->post_category_id);
        $this->get('/live-stone')->assertOk()->assertSee('Live words')->assertDontSee('Draft words')->assertSee($current->path, false);

        $this->actingAs($admin)->postJson('/admin/posts/'.$post->id.'/studio/publish')
            ->assertOk()
            ->assertJsonPath('url', url('/live-stone'));
        $this->get('/live-stone')->assertOk()->assertSee('Draft words')->assertSee($next->path, false);
        $this->assertSame($yards->id, $post->refresh()->post_category_id);
    }

    public function test_studio_save_keeps_bold_text_and_the_chosen_category(): void
    {
        $admin = User::factory()->create();
        $category = PostCategory::query()->create(['name' => 'Guides', 'slug' => 'guides']);
        $post = $this->studioPost();

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'metadata' => [
                'title' => 'Courtyard note',
                'excerpt' => 'A long enough excerpt for a sandstone courtyard story.',
                'categoryId' => $category->id,
            ],
            'document' => $this->document([
                ['type' => 'paragraph', 'content' => [[
                    'type' => 'text',
                    'text' => 'Warm stone',
                    'marks' => [['type' => 'bold']],
                ]]],
            ]),
        ])->assertOk();

        $post->refresh();
        $this->assertSame($category->id, $post->post_category_id);
        $this->assertSame('bold', $post->body_document['content'][0]['content'][0]['marks'][0]['type']);
    }

    public function test_a_photograph_added_to_a_section_is_shown_with_its_address(): void
    {
        $admin = User::factory()->create();
        $media = JournalMedia::query()->create([
            'path' => 'storage/journal/9/photo-stone.jpg',
            'alt' => 'Sandstone chips',
        ]);
        $post = $this->studioPost();
        $post->body_document = $this->document([
            ['type' => 'image', 'attrs' => ['mediaId' => $media->id, 'alt' => 'Sandstone chips']],
            ['type' => 'gallery', 'attrs' => ['mode' => 'grid', 'items' => [
                ['mediaId' => $media->id, 'alt' => 'Sandstone chips'],
            ]]],
        ]);
        $post->save();

        $this->actingAs($admin)->get('/admin/posts/'.$post->id.'/studio')
            ->assertOk()
            ->assertSee('storage/journal/9/photo-stone.jpg', false);

        $saved = $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'document' => $post->body_document,
        ]);

        $saved->assertOk();
        $this->assertStringContainsString('storage/journal/9/photo-stone.jpg', (string) $saved->json('document.content.0.attrs.url'));
        $this->assertStringContainsString('storage/journal/9/photo-stone.jpg', (string) $saved->json('document.content.1.attrs.items.0.url'));
        $this->assertSame($media->id, $post->refresh()->body_document['content'][0]['attrs']['mediaId']);
    }

    public function test_publish_stops_when_a_check_is_blocked(): void
    {
        $admin = User::factory()->create();
        $post = $this->studioPost();

        $this->actingAs($admin)->putJson('/admin/posts/'.$post->id.'/studio', [
            'baseVersion' => 1,
            'metadata' => ['title' => 'Untitled article', 'excerpt' => 'Too short'],
            'document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Not ready']]],
            ]),
        ])->assertOk();

        $this->actingAs($admin)->postJson('/admin/posts/'.$post->id.'/studio/publish')
            ->assertStatus(422);

        $this->assertFalse($post->refresh()->is_published);
    }

    public function test_converting_a_classic_article_keeps_the_original_body(): void
    {
        $admin = User::factory()->create();
        $post = Post::query()->create([
            'slug' => 'classic-note',
            'title' => 'Classic note',
            'summary' => 'A classic article that is long enough to publish later on.',
            'body' => '<p>Original quarry note.</p>',
            'content_source' => 'classic',
            'status' => 'published',
            'is_published' => true,
        ]);

        $this->actingAs($admin)->postJson('/admin/posts/'.$post->id.'/studio/convert')->assertOk();

        $post->refresh();
        $this->assertSame('<p>Original quarry note.</p>', $post->body);
        $this->assertSame('studio', $post->content_source);
        $this->get('/classic-note')->assertOk()->assertSee('Original quarry note.');
    }

    public function test_a_draft_is_hidden_and_a_missing_stone_does_not_break_the_article(): void
    {
        Post::query()->create([
            'slug' => 'hidden-studio-draft',
            'title' => 'Hidden studio draft',
            'summary' => '',
            'body' => '<p>Still in the workshop.</p>',
            'content_source' => 'studio',
            'status' => 'draft',
            'is_published' => false,
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Still in the workshop.']]],
            ]),
        ]);
        Post::query()->create([
            'slug' => 'missing-stone',
            'title' => 'Missing stone',
            'summary' => $this->readyExcerpt(),
            'body' => '',
            'content_source' => 'studio',
            'status' => 'published',
            'is_published' => true,
            'published_on' => now()->toDateString(),
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'The stone is gone.']]],
                ['type' => 'productSpotlight', 'attrs' => ['productId' => 999999]],
            ]),
        ]);

        $this->get('/hidden-studio-draft')->assertNotFound();
        $this->get('/missing-stone')
            ->assertOk()
            ->assertSee('The stone is gone.')
            ->assertSee('This stone could not be found.')
            ->assertSee('og:type" content="article"', false)
            ->assertSee('BlogPosting', false);
    }

    public function test_a_scheduled_story_appears_only_after_the_publish_command(): void
    {
        Post::query()->create([
            'slug' => 'future-stone-note',
            'title' => 'Future stone note',
            'summary' => $this->readyExcerpt(),
            'body' => '',
            'content_source' => 'studio',
            'status' => 'scheduled',
            'is_published' => false,
            'scheduled_for' => now()->addMinutes(5),
            'lock_version' => 1,
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'The crate is not ready.']]],
            ]),
        ]);

        $this->get('/blog')->assertOk()->assertDontSee('Future stone note');
        $this->get('/future-stone-note')->assertNotFound();

        $this->travel(6)->minutes();
        $this->artisan('blogs:publish-scheduled')->assertSuccessful();

        $this->get('/future-stone-note')->assertOk()->assertSee('The crate is not ready.');
        $this->assertSame('published', Post::query()->where('slug', 'future-stone-note')->value('status'));
    }

    public function test_preview_shows_a_recent_story_and_the_draft_text_and_image_block(): void
    {
        $admin = User::factory()->create();
        Post::query()->create([
            'slug' => 'makrana-yard-notes',
            'title' => 'Makrana yard notes',
            'summary' => 'A short note from the yard.',
            'body' => 'The recent story body.',
            'content_source' => 'classic',
            'status' => 'published',
            'is_published' => true,
            'published_on' => '2026-11-01',
            'view_count' => 4,
        ]);
        $post = Post::query()->create([
            'slug' => 'granite-elegance-draft',
            'title' => 'Granite elegance',
            'summary' => 'The published summary stays on the live article.',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'published',
            'is_published' => true,
            'published_on' => '2026-10-01',
            'view_count' => 7,
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Published quarry note that preview must not show']]],
            ]),
        ]);
        PostDraft::query()->create([
            'post_id' => $post->id,
            'title' => 'Granite elegance',
            'slug' => 'granite-elegance-draft',
            'excerpt' => 'A draft excerpt for the preview.',
            'document' => $this->document([
                ['type' => 'splitSection', 'attrs' => [
                    'heading' => 'Draft split beside the photograph',
                    'body' => 'The draft places the photograph beside this paragraph.',
                    'layout' => 'image-right',
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->get('/admin/posts/'.$post->id.'/preview')
            ->assertOk()
            ->assertSee('[Preview] Granite elegance')
            ->assertSee('Makrana yard notes')
            ->assertSee('rich-split', false)
            ->assertSee('Draft split beside the photograph')
            ->assertDontSee('Published quarry note that preview must not show');

        $this->assertSame(7, $post->refresh()->view_count);
    }

    private function readyExcerpt(): string
    {
        return 'Sandstone, limestone, marble and granite notes from the Jodhpur yard, written so a buyer can judge the colour, the finish, and how the surface will wear outdoors.';
    }

    private function studioPost(): Post
    {
        return Post::query()->create([
            'slug' => 'studio-draft-'.fake()->unique()->numerify('###'),
            'title' => 'Studio draft',
            'summary' => '',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'draft',
            'is_published' => false,
            'lock_version' => 1,
            'body_document' => $this->document([
                ['type' => 'paragraph', 'content' => []],
            ]),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    private function document(array $content): array
    {
        return [
            'type' => 'doc',
            'schemaVersion' => 1,
            'content' => $content,
        ];
    }
}
