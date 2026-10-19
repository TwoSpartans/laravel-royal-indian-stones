<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
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
            ->assertSee('Buying guide')
            ->assertSee('What to look for before you order')
            ->assertSee('Room guide');

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
            'summary' => 'A published note that is long enough for the excerpt check.',
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
            'metadata' => ['excerpt' => 'A published note that is long enough for the excerpt check.'],
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
