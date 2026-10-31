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
            ->assertSee('Contact Us')
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
        $this->assertFileExists($this->journalFile((string) $settings->cover_image));

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Notes from')
            ->assertSee('the quarry.');

        $hash = substr(md5((string) $settings->cover_image), 0, 16);
        foreach ([
            public_path('assets/img/optimized/'.$hash.'-*'),
            storage_path('app/public/img/optimized/'.$hash.'-*'),
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $variant) {
                unlink($variant);
            }
        }
        $coverFile = $this->journalFile((string) $settings->cover_image);
        if (is_file($coverFile)) {
            unlink($coverFile);
        }
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

    public function test_journal_editor_shows_every_field_and_the_published_stories(): void
    {
        $older = $this->story([
            'title' => 'Older courtyard note',
            'slug' => 'older-courtyard-note',
            'published_on' => '2024-01-02',
        ]);
        $newer = $this->story([
            'title' => 'Newer quarry note',
            'slug' => 'newer-quarry-note',
            'published_on' => '2024-08-02',
        ]);
        $hidden = $this->story([
            'title' => 'Hidden quarry note',
            'slug' => 'hidden-quarry-note',
            'is_published' => false,
            'status' => 'draft',
            'published_on' => '2024-09-02',
        ]);
        $settings = JournalSetting::current();
        $settings->edit_post_ids = [$hidden->id];
        $settings->save();

        $admin = User::factory()->create();
        $response = $this->actingAs($admin)->get('/admin/journal');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="journal-settings"'));
        $this->assertSame(3, substr_count($html, 'name="edit_posts[]"'));
        $this->assertSame(2, substr_count($html, 'name="closer_posts[]"'));
        $response->assertSee('Journal page editor')
            ->assertSee('View public journal')
            ->assertSee('Save Journal Page')
            ->assertSee('Existing content loaded')
            ->assertSee('Approximate preview of the public /blog landing, not the live page.')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('data-open-section="cover"', false)
            ->assertSee('Featured story')
            ->assertSee('>Preview<', false)
            ->assertSee('Hidden quarry note (hidden)')
            ->assertDontSee('Journal page saved')
            ->assertSee('Leave it blank to feature the latest published story')
            ->assertSee('Select a published story')
            ->assertSee('Use selected story')
            ->assertSee('Clear slot leaves the field blank.')
            ->assertDontSee('data-journal-panel="maker"', false)
            ->assertDontSee('data-journal-panel="closer"', false)
            ->assertDontSee('data-journal-panel="overview"', false)
            ->assertDontSee('Sculpting Eternity')
            ->assertDontSee('Introducing the World of Stone Chips');
        foreach ([
            'eyebrow',
            'heading_lead',
            'heading_emphasis',
            'introduction',
            'caption',
            'cover_image_alt',
            'cover',
            'edit_kicker',
            'edit_heading',
            'closer_kicker',
            'closer_heading',
            'maker_post_id',
            'maker_path',
            'maker_eyebrow',
            'maker_heading',
            'maker_body',
            'maker_label',
            'maker_image_alt',
            'maker_image',
        ] as $name) {
            $response->assertSee('name="'.$name.'"', false);
        }
        $this->assertStringContainsString('journal-preview-feature-title">Newer quarry note', $html);
        $this->assertStringContainsString('journal-preview-story-title">Older courtyard note', $html);
        $this->assertStringNotContainsString('journal-preview-feature-title">Hidden quarry note', $html);
        $this->assertStringNotContainsString('journal-preview-story-title">Newer quarry note', $html);
        $this->assertStringNotContainsString('journal-preview-feature-title">'.$older->title, $html);
        $this->assertSame([$hidden->id], array_map(intval(...), JournalSetting::query()->first()->edit_post_ids));

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Stories in')
            ->assertSee('Newer quarry note')
            ->assertSee('Older courtyard note')
            ->assertDontSee('Hidden quarry note')
            ->assertDontSee('Approximate preview')
            ->assertDontSee('Journal page editor');
    }

    public function test_journal_save_keeps_every_section_and_stores_a_repeated_story_once(): void
    {
        $first = $this->story(['title' => 'First chosen story', 'slug' => 'first-chosen-story', 'published_on' => '2024-03-02']);
        $second = $this->story(['title' => 'Second chosen story', 'slug' => 'second-chosen-story', 'published_on' => '2024-04-02']);
        $third = $this->story(['title' => 'Third listed story', 'slug' => 'third-listed-story', 'published_on' => '2024-05-02']);
        $admin = User::factory()->create();

        $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
            'heading_lead' => 'Notes from',
            'heading_emphasis' => 'the yard.',
            'edit_kicker' => 'The yard edit',
            'edit_heading' => 'Three stones',
            'edit_posts' => [$first->id, $first->id, $second->id],
            'closer_kicker' => 'Closer yard',
            'closer_heading' => 'Two details',
            'closer_posts' => [$third->id, $third->id],
            'maker_post_id' => $third->id,
            'maker_eyebrow' => 'Workshop',
            'maker_heading' => 'Hands in the yard',
            'maker_body' => 'The masons set the step.',
            'maker_label' => 'Visit the yard',
            'maker_path' => '/aboutus',
        ]))->assertRedirect(route('admin.journal.edit'))
            ->assertSessionHas('status', 'Journal page saved.');

        $settings = JournalSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertSame('Notes from', $settings->heading_lead);
        $this->assertSame('the yard.', $settings->heading_emphasis);
        $this->assertSame('The yard edit', $settings->edit_kicker);
        $this->assertSame('Three stones', $settings->edit_heading);
        $this->assertSame([$first->id, $second->id], array_map(intval(...), $settings->edit_post_ids));
        $this->assertSame('Closer yard', $settings->closer_kicker);
        $this->assertSame('Two details', $settings->closer_heading);
        $this->assertSame([$third->id], array_map(intval(...), $settings->closer_post_ids));
        $this->assertSame($third->id, (int) $settings->maker_post_id);
        $this->assertSame('Hands in the yard', $settings->maker_heading);
        $this->assertSame('The masons set the step.', $settings->maker_body);
        $this->assertSame('/aboutus', $settings->maker_path);

        $editor = $this->actingAs($admin)->get('/admin/journal');
        $editor->assertOk()
            ->assertSee('Journal page saved')
            ->assertSee('value="Notes from"', false)
            ->assertSee('The yard edit')
            ->assertSee('Hands in the yard')
            ->assertSee('value="'.$first->id.'" selected', false);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Notes from')
            ->assertSee('the yard.')
            ->assertSee('First chosen story')
            ->assertSee('Second chosen story')
            ->assertDontSee('The yard edit')
            ->assertDontSee('Closer yard')
            ->assertDontSee('Hands in the yard');
    }

    public function test_journal_save_stores_photographs_and_can_remove_only_the_maker_photograph(): void
    {
        $admin = User::factory()->create();
        $coverPath = null;
        $makerPath = null;

        try {
            $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
                'heading_lead' => 'Notes from',
                'cover' => UploadedFile::fake()->image('cover.jpg', 80, 60),
                'maker_image' => UploadedFile::fake()->image('maker.jpg', 80, 60),
                'maker_path' => '/aboutus',
                'maker_heading' => 'Hands in the yard',
            ]))->assertRedirect(route('admin.journal.edit'));

            $settings = JournalSetting::query()->first();
            $this->assertNotNull($settings);
            $coverPath = $settings->cover_image;
            $makerPath = $settings->maker_image;
            $this->assertNotNull($coverPath);
            $this->assertNotNull($makerPath);
            $this->assertNotSame($coverPath, $makerPath);
            $this->assertFileExists($this->journalFile($coverPath));
            $this->assertFileExists($this->journalFile($makerPath));
            $this->assertSame('Notes from', $settings->heading_lead);

            $this->get('/blog')
                ->assertOk()
                ->assertSee('Notes from')
                ->assertSee($coverPath, false)
                ->assertDontSee('Hands in the yard');

            $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
                'heading_lead' => 'Notes from',
                'maker_path' => '/aboutus',
                'maker_heading' => 'Hands in the yard',
                'remove_maker_image' => '1',
            ]))->assertRedirect(route('admin.journal.edit'));

            $settings->refresh();
            $this->assertSame($coverPath, $settings->cover_image);
            $this->assertNull($settings->maker_image);
            $this->assertFileExists($this->journalFile($coverPath));
            $this->assertFileDoesNotExist($this->journalFile($makerPath));
            $this->assertSame('Notes from', $settings->heading_lead);
        } finally {
            $this->deleteJournalUpload(is_string($coverPath) ? $coverPath : null);
            $this->deleteJournalUpload(is_string($makerPath) ? $makerPath : null);
        }
    }

    public function test_an_invalid_cover_upload_keeps_the_saved_photograph_and_the_draft(): void
    {
        $admin = User::factory()->create();
        $coverPath = null;

        try {
            $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
                'cover' => UploadedFile::fake()->image('cover.jpg', 40, 40),
            ]))->assertRedirect(route('admin.journal.edit'));

            $settings = JournalSetting::query()->first();
            $this->assertNotNull($settings);
            $coverPath = $settings->cover_image;
            $this->assertIsString($coverPath);
            $this->assertFileExists($this->journalFile($coverPath));

            $response = $this->actingAs($admin)
                ->from('/admin/journal')
                ->followingRedirects()
                ->put('/admin/journal', $this->journalPayload([
                    'heading_lead' => 'Draft heading',
                    'cover' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
                ]));

            $response->assertOk()
                ->assertSee('The cover field must be a file of type: jpg, jpeg, png, webp.')
                ->assertSee('value="Draft heading"', false)
                ->assertSee($coverPath, false)
                ->assertDontSee('Journal page saved');
            $settings->refresh();
            $this->assertSame($coverPath, $settings->cover_image);
            $this->assertSame('Stories in', $settings->heading_lead);
            $this->assertFileExists($this->journalFile($coverPath));
        } finally {
            $this->deleteJournalUpload(is_string($coverPath) ? $coverPath : null);
        }
    }

    public function test_the_same_story_can_be_saved_in_both_groups_and_appears_once_on_the_blog(): void
    {
        $shared = $this->story([
            'title' => 'Shared courtyard note',
            'slug' => 'shared-courtyard-note',
            'published_on' => '2024-08-02',
        ]);
        $this->story([
            'title' => 'Later yard note',
            'slug' => 'later-yard-note',
            'published_on' => '2024-01-02',
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
            'edit_posts' => [$shared->id],
            'closer_posts' => [$shared->id],
        ]))->assertRedirect(route('admin.journal.edit'));

        $settings = JournalSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertSame([$shared->id], array_map(intval(...), $settings->edit_post_ids));
        $this->assertSame([$shared->id], array_map(intval(...), $settings->closer_post_ids));

        $blog = $this->get('/blog');
        $blog->assertOk()
            ->assertSee('Shared courtyard note')
            ->assertSee('Later yard note')
            ->assertDontSee('02 / A closer look');
        $this->assertSame(1, substr_count($blog->getContent(), '<article class="journal-card"'));
    }

    public function test_maker_page_copy_is_saved_and_stays_off_the_public_journal(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put('/admin/journal', $this->journalPayload([
            'maker_path' => '/aboutus',
            'maker_heading' => 'Hands in the yard',
            'maker_eyebrow' => 'The hands behind the craft',
            'maker_label' => 'Meet the makers',
        ]))->assertRedirect(route('admin.journal.edit'));

        $settings = JournalSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertSame('/aboutus', $settings->maker_path);
        $this->assertSame('Hands in the yard', $settings->maker_heading);
        $this->assertNull($settings->maker_post_id);

        $this->get('/blog')
            ->assertOk()
            ->assertDontSee('Hands in the yard')
            ->assertDontSee('Meet the makers');
    }

    public function test_rejected_journal_save_keeps_the_draft_and_opens_the_maker_section(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->from('/admin/journal')
            ->followingRedirects()
            ->put('/admin/journal', $this->journalPayload([
                'heading_lead' => 'Notes from',
                'maker_path' => '/not-a-real-page',
                'maker_heading' => 'Nowhere',
            ]));

        $response->assertOk()
            ->assertSee('Choose a page that already exists on the site.')
            ->assertSee('Check the highlighted fields')
            ->assertSee('value="Notes from"', false)
            ->assertSee('value="/not-a-real-page"', false)
            ->assertSee('data-open-section="cover"', false)
            ->assertSee('journal-panel is-open" data-journal-panel="cover"', false)
            ->assertDontSee('Journal page saved');
        $this->assertSame('Stories in', JournalSetting::query()->first()->heading_lead);
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

    private function deleteJournalUpload(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $hash = substr(md5($path), 0, 16);
        foreach ([
            public_path('assets/img/optimized/'.$hash.'-*'),
            storage_path('app/public/img/optimized/'.$hash.'-*'),
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $variant) {
                if (is_file($variant)) {
                    unlink($variant);
                }
            }
        }

        $fullPath = $this->journalFile($path);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    private function journalFile(string $path): string
    {
        if (str_starts_with($path, 'storage/')) {
            return storage_path('app/public/'.substr($path, strlen('storage/')));
        }

        return public_path($path);
    }
}
