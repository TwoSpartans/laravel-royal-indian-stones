<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JournalSetting;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class JournalPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_shows_published_stories_and_hides_drafts_and_future_posts(): void
    {
        $visible = $this->story([
            'title' => 'Choosing sandstone for floors',
            'slug' => 'choosing-sandstone',
            'summary' => '',
            'body' => '<p>'.trim(str_repeat('stone ', 400)).'</p>',
        ]);
        $this->story([
            'title' => 'Unpublished quarry note',
            'slug' => 'unpublished-quarry-note',
            'is_published' => false,
        ]);
        $this->story([
            'title' => 'Future quarry note',
            'slug' => 'future-quarry-note',
            'scheduled_for' => now()->addDay(),
        ]);
        $this->story([
            'title' => 'Draft still hidden',
            'slug' => 'draft-still-hidden',
            'status' => 'draft',
        ]);

        $response = $this->get('/blog');

        $response->assertOk()
            ->assertSee('Stories in')
            ->assertSee('Indian stone.')
            ->assertSee('Choosing sandstone for floors')
            ->assertSee('2 min read')
            ->assertSee('Latest stories')
            ->assertSee('Read the story')
            ->assertDontSee('Unpublished quarry note')
            ->assertDontSee('Future quarry note')
            ->assertDontSee('Draft still hidden')
            ->assertDontSee('The quiet beauty of woven cane')
            ->assertDontSee('Stories pagination');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(1, substr_count($response->getContent(), 'id="newsletter_form"'));
        $response->assertSee('action="'.route('catalogue').'"', false)
            ->assertSee('Request a Quote')
            ->assertSee('Join Our Newsletter')
            ->assertSee('href="'.url('/'.$visible->slug).'"', false);
    }

    public function test_one_story_keeps_the_edit_and_leaves_out_the_closer_section(): void
    {
        $this->story(['title' => 'Only one courtyard note', 'body' => '<p>Hello</p>', 'summary' => '']);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Only one courtyard note')
            ->assertSee('1 min read')
            ->assertDontSee('Know the materials. Love the details.');
    }

    public function test_empty_journal_offers_the_shop_and_hides_the_grids(): void
    {
        $this->get('/blog')
            ->assertOk()
            ->assertSee('Stories are on the way.')
            ->assertSee('Browse the stones')
            ->assertSee('About the workshop')
            ->assertSee('href="'.url('/products').'"', false)
            ->assertSee('href="'.url('/aboutus').'"', false)
            ->assertDontSee('journal-card')
            ->assertDontSee('Stories pagination')
            ->assertDontSee('Latest stories');
    }

    public function test_category_search_and_pagination_share_one_archive(): void
    {
        $guides = PostCategory::query()->create(['name' => 'Guides', 'slug' => 'guides']);
        $care = PostCategory::query()->create(['name' => 'Stone care', 'slug' => 'stone-care']);
        $this->story([
            'title' => 'Jaisalmer laying notes',
            'slug' => 'jaisalmer-laying-notes',
            'post_category_id' => $guides->id,
            'body' => '<p>Jaisalmer sandstone needs a firm bed.</p>',
        ]);
        $this->story([
            'title' => 'Cleaning limestone',
            'slug' => 'cleaning-limestone',
            'post_category_id' => $care->id,
        ]);
        PostCategory::query()->create(['name' => 'Empty topic', 'slug' => 'empty-topic']);
        $stone = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'position' => 1,
        ]);
        Product::query()->create([
            'category_id' => $stone->id,
            'name' => 'UniqueProductToken',
            'slug' => 'unique-product-token',
            'finishes' => [],
            'applications' => [],
            'is_published' => true,
        ]);

        $this->get('/blog?category=guides')
            ->assertOk()
            ->assertSee('Jaisalmer laying notes')
            ->assertDontSee('Cleaning limestone')
            ->assertDontSee('From the quarry to the building.')
            ->assertSee('name="category"', false);

        $this->get('/blog?category=empty-topic')
            ->assertOk()
            ->assertSee('No stories in this topic yet.')
            ->assertSee('All stories');

        $this->get('/blog?category=missing-topic')->assertNotFound();

        $this->get('/blog?q=Jaisalmer')
            ->assertOk()
            ->assertSee('Jaisalmer laying notes')
            ->assertSee('1 story matching')
            ->assertDontSee('Cleaning limestone');

        $this->get('/blog?q=UniqueProductToken')
            ->assertOk()
            ->assertSee('No stories match')
            ->assertDontSee('href="'.url('/unique-product-token').'"', false);

        $escaped = $this->get('/blog?q='.urlencode('<script>alert(1)</script>'));
        $escaped->assertOk();
        $this->assertStringContainsString('value="&lt;script&gt;alert(1)&lt;/script&gt;"', $escaped->getContent());
        $this->assertStringNotContainsString('value="<script>', $escaped->getContent());

        $this->get('/blogs?category=guides')
            ->assertStatus(301)
            ->assertRedirect('/blog?category=guides');
    }

    public function test_archive_pages_keep_filters_and_do_not_repeat_the_landing_features(): void
    {
        $guides = PostCategory::query()->create(['name' => 'Guides', 'slug' => 'guides']);
        foreach (range(1, 7) as $day) {
            $this->story([
                'slug' => 'guide-day-'.$day,
                'title' => 'Guide day '.$day,
                'post_category_id' => $guides->id,
                'published_on' => sprintf('2024-01-%02d', $day),
            ]);
        }
        $settings = JournalSetting::current();
        $settings->maker_heading = 'The hands behind the details.';
        $settings->maker_path = '/aboutus';
        $settings->save();

        $landing = $this->get('/blog');
        $landing->assertOk()
            ->assertSee('Guide day 7')
            ->assertSee('Latest stories')
            ->assertSee('Guide day 1')
            ->assertDontSee('The hands behind the details.');

        $page = $this->get('/blog?category=guides&page=2');
        $page->assertOk()
            ->assertSee('Guide day 1')
            ->assertDontSee('Guide day 7')
            ->assertDontSee('The hands behind the details.')
            ->assertSee('Stories pagination')
            ->assertSee('aria-current="page"', false)
            ->assertSee('category=guides', false)
            ->assertSee('page=2', false)
            ->assertSee('rel="prev"', false);
        $this->assertStringContainsString('page=2', $page->getContent());
        $this->assertMatchesRegularExpression('/rel="canonical" href="[^"]*page=2/', $page->getContent());
    }

    public function test_a_chosen_story_leads_the_journal_and_a_hidden_story_does_not(): void
    {
        $this->story(['title' => 'A visible story', 'slug' => 'a-visible-story']);
        $hidden = $this->story([
            'title' => 'Hidden maker story',
            'slug' => 'hidden-maker-story',
            'is_published' => false,
            'status' => 'draft',
        ]);

        $this->get('/blog')->assertOk()->assertSee('A visible story')->assertDontSee('Hidden maker story');

        $settings = JournalSetting::current();
        $settings->edit_post_ids = [$hidden->id];
        $settings->save();
        $this->get('/blog')->assertOk()->assertDontSee('Hidden maker story')->assertSee('A visible story');

        $published = $this->story(['title' => 'Meet the stone masons', 'slug' => 'meet-the-stone-masons']);
        $settings->edit_post_ids = [$published->id];
        $settings->save();

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Meet the stone masons')
            ->assertSee('href="'.url('/meet-the-stone-masons').'"', false)
            ->assertSee('Read the story');
    }

    public function test_a_due_story_is_published_and_a_blocked_schedule_stays_hidden(): void
    {
        Post::query()->create([
            'slug' => 'due-story',
            'title' => 'A story that came due',
            'summary' => 'A published note that is long enough for the excerpt check.',
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
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'The stone is ready.']]],
                ],
            ],
        ]);
        Post::query()->create([
            'slug' => 'blocked-schedule',
            'title' => 'Blocked schedule note',
            'summary' => 'Short',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'scheduled',
            'is_published' => false,
            'scheduled_for' => now()->subMinute(),
            'body_document' => ['type' => 'doc', 'schemaVersion' => 1, 'content' => []],
        ]);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('A story that came due')
            ->assertDontSee('Blocked schedule note');

        $this->assertTrue(Post::query()->where('slug', 'due-story')->value('is_published'));
        $this->assertSame('schedule_failed', Post::query()->where('slug', 'blocked-schedule')->value('status'));
        $this->get('/due-story')->assertOk()->assertSee('BlogPosting', false)->assertSee('The stone is ready.');
    }

    public function test_long_titles_are_not_cut_off_in_the_html(): void
    {
        $title = 'A very long story title about sandstone courtyards and the care they need over many seasons of weather';
        $summary = 'This excerpt stays complete so a reader can judge the story before opening it, even when the sentence runs longer than the card in the design.';
        $this->story([
            'title' => $title,
            'summary' => $summary,
        ]);

        $this->get('/blog')
            ->assertOk()
            ->assertSee($title)
            ->assertSee($summary);
    }

    public function test_guest_cannot_edit_the_journal_and_an_editor_can_save_it(): void
    {
        $this->get('/admin/journal')->assertRedirect('/admin/login');

        $admin = User::factory()->create();
        $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
            'maker_path' => '/not-a-real-page',
            'maker_heading' => 'Nowhere',
        ]))->assertSessionHasErrors([
            'maker_path' => 'Choose a page that already exists on the site.',
        ]);

        $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
            'heading_lead' => 'Notes from',
            'heading_emphasis' => 'the quarry.',
            'cover' => UploadedFile::fake()->image('cover.jpg', 800, 600),
            'cover_image_alt' => 'A carved cabinet in warm light',
        ]))->assertRedirect(route('admin.journal.edit'));

        $settings = JournalSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertSame('Notes from', $settings->heading_lead);
        $this->assertNotNull($settings->cover_image);
        $this->assertFileExists(public_path((string) $settings->cover_image));

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Notes from')
            ->assertSee('the quarry.');

        $hash = substr(md5((string) $settings->cover_image), 0, 16);
        foreach (glob(public_path('assets/img/optimized/'.$hash.'-*')) ?: [] as $variant) {
            unlink($variant);
        }
        unlink(public_path((string) $settings->cover_image));
    }

    public function test_a_search_quote_does_not_reveal_an_unpublished_story(): void
    {
        $this->story(['title' => 'Public courtyard note', 'body' => '<p>Public words.</p>']);
        $this->story([
            'title' => 'Secret quarry plan',
            'slug' => 'secret-quarry-plan',
            'is_published' => false,
            'body' => '<p>Secret words.</p>',
        ]);

        $this->get('/blog?q='.urlencode("%' OR '1'='1"))
            ->assertOk()
            ->assertDontSee('Secret quarry plan');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function story(array $overrides = []): Post
    {
        $number = Post::query()->count() + 1;

        return Post::query()->create(array_merge([
            'slug' => 'journal-story-'.$number,
            'title' => 'Journal story '.$number,
            'summary' => 'A short summary of the story.',
            'body' => '<p>Stone for a courtyard.</p>',
            'is_published' => true,
            'status' => 'published',
            'published_on' => '2024-05-'.str_pad((string) min($number, 28), 2, '0', STR_PAD_LEFT),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function journalPayload(array $overrides = []): array
    {
        $defaults = JournalSetting::defaults();

        return array_merge([
            'eyebrow' => $defaults['eyebrow'],
            'heading_lead' => $defaults['heading_lead'],
            'heading_emphasis' => $defaults['heading_emphasis'],
            'introduction' => $defaults['introduction'],
            'caption' => $defaults['caption'],
            'cover_image_alt' => $defaults['cover_image_alt'],
            'edit_kicker' => $defaults['edit_kicker'],
            'edit_heading' => $defaults['edit_heading'],
            'closer_kicker' => $defaults['closer_kicker'],
            'closer_heading' => $defaults['closer_heading'],
            'maker_eyebrow' => $defaults['maker_eyebrow'],
            'maker_label' => $defaults['maker_label'],
        ], $overrides);
    }
}
