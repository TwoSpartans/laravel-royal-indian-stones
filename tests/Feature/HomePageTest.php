<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomeImage;
use App\Models\HomeSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_home_editor(): void
    {
        $this->get('/admin/home')->assertRedirect('/admin/login');
    }

    public function test_a_visitor_without_admin_access_cannot_open_the_home_editor(): void
    {
        $visitor = User::factory()->create();
        $visitor->forceFill(['is_site_admin' => false])->save();

        $this->actingAs($visitor)->get('/admin/home')->assertForbidden();
    }

    public function test_home_editor_lists_collection_covers_and_skips_article_categories(): void
    {
        $this->sandstone();
        Category::query()->create([
            'name' => 'Stone articles',
            'slug' => 'stone-articles',
            'position' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/home')
            ->assertOk()
            ->assertSee('Explore the collection')
            ->assertSee('Opening slides')
            ->assertSee('Natural Indian Stones')
            ->assertSee('agra-red-sandstone-natural-mosaic-tiles.jpg', false)
            ->assertSee('Sandstone')
            ->assertDontSee('Stone articles');
    }

    public function test_replacing_an_explore_photograph_shows_it_on_the_home_page_and_drops_the_previous_file(): void
    {
        $this->sandstone();
        $admin = User::factory()->create();
        $first = null;
        $second = null;

        try {
            $this->actingAs($admin)->put(route('admin.home.update', 'explore'), [
                'photo' => [
                    'explore' => [
                        'sandstone' => UploadedFile::fake()->image('cover.jpg', 800, 500),
                    ],
                ],
            ])->assertRedirect(route('admin.home.edit'));

            $first = HomeImage::query()->where('section', 'explore')->where('slot', 'sandstone')->value('path');
            $this->assertIsString($first);
            $this->assertFileExists(public_path($first));

            $this->get('/')
                ->assertOk()
                ->assertSee($first, false)
                ->assertDontSee('bced8b2cfeced7e0-380.webp', false);

            $this->actingAs($admin)->put(route('admin.home.update', 'explore'), [
                'photo' => [
                    'explore' => [
                        'sandstone' => UploadedFile::fake()->image('next.jpg', 800, 500),
                    ],
                ],
            ])->assertRedirect(route('admin.home.edit'));

            $second = HomeImage::query()->where('section', 'explore')->where('slot', 'sandstone')->value('path');
            $this->assertIsString($second);
            $this->assertNotSame($first, $second);
            $this->assertFileDoesNotExist(public_path($first));
            $this->assertFileExists(public_path($second));

            $this->get('/')->assertOk()->assertSee($second, false);
        } finally {
            $this->deleteUploaded($first);
            $this->deleteUploaded($second);
        }
    }

    public function test_restoring_an_explore_photograph_returns_the_earlier_cover(): void
    {
        $this->sandstone();
        $admin = User::factory()->create();
        $path = null;

        try {
            $this->actingAs($admin)->put(route('admin.home.update', 'explore'), [
                'photo' => [
                    'explore' => [
                        'sandstone' => UploadedFile::fake()->image('cover.jpg', 800, 500),
                    ],
                ],
            ])->assertRedirect(route('admin.home.edit'));

            $path = HomeImage::query()->where('slot', 'sandstone')->value('path');
            $this->assertIsString($path);

            $this->actingAs($admin)->put(route('admin.home.update', 'explore'), [
                'restore' => [
                    'explore' => [
                        'sandstone' => '1',
                    ],
                ],
            ])->assertRedirect(route('admin.home.edit'));

            $this->assertDatabaseCount('home_images', 0);
            $this->assertFileDoesNotExist(public_path($path));
            $this->get('/')
                ->assertOk()
                ->assertSee('assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg', false)
                ->assertDontSee($path, false);
        } finally {
            $this->deleteUploaded(is_string($path) ? $path : null);
        }
    }

    public function test_an_explore_description_is_escaped_on_the_home_page(): void
    {
        $this->sandstone();
        $script = '<script>alert(1)</script>';

        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'explore'), [
            'alt' => [
                'explore' => [
                    'sandstone' => $script,
                ],
            ],
        ])->assertRedirect(route('admin.home.edit'));

        $this->get('/')
            ->assertOk()
            ->assertDontSee($script, false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $this->actingAs(User::factory()->create())
            ->get('/admin/home')
            ->assertOk()
            ->assertDontSee($script, false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_text_file_is_rejected_for_an_explore_photograph(): void
    {
        $this->sandstone();

        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'explore'), [
            'photo' => [
                'explore' => [
                    'sandstone' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
                ],
            ],
        ])->assertSessionHasErrors([
            'photo.explore.sandstone' => 'Use a JPG, PNG, or WebP image.',
        ]);

        $this->assertDatabaseCount('home_images', 0);
    }

    public function test_a_long_explore_description_is_rejected(): void
    {
        $this->sandstone();

        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'explore'), [
            'alt' => [
                'explore' => [
                    'sandstone' => str_repeat('a', 181),
                ],
            ],
        ])->assertSessionHasErrors([
            'alt.explore.sandstone' => 'Keep the description under 180 characters.',
        ]);

        $this->assertDatabaseCount('home_images', 0);
    }

    public function test_an_unknown_explore_slot_is_ignored(): void
    {
        $this->sandstone();

        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'explore'), [
            'photo' => [
                'explore' => [
                    'not-a-real-stone' => UploadedFile::fake()->image('extra.jpg', 40, 40),
                ],
            ],
            'alt' => [
                'explore' => [
                    'not-a-real-stone' => 'Should not be stored',
                ],
            ],
        ])->assertRedirect(route('admin.home.edit'));

        $this->assertDatabaseCount('home_images', 0);
        $this->assertSame([], glob(public_path('assets/img/home/explore/*')) ?: []);
    }

    public function test_an_unknown_home_section_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/home/footer')
            ->assertNotFound();
    }

    public function test_supplier_wording_can_be_changed_and_cleared_back_to_the_original(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.home.update', 'supplier'), [
            'copy' => [
                'supplier' => [
                    'heading' => 'Stone from Jodhpur',
                    'body' => 'Cut and packed in the workshop.',
                    'button_label' => 'Ask for a price',
                ],
            ],
        ])->assertRedirect(route('admin.home.edit'))->assertSessionHas('home_section', 'supplier');

        $this->get('/')
            ->assertOk()
            ->assertSee('Stone from Jodhpur')
            ->assertSee('Cut and packed in the workshop.')
            ->assertSee('Ask for a price');

        $this->actingAs($admin)->put(route('admin.home.update', 'supplier'), [
            'copy' => [
                'supplier' => [
                    'heading' => '',
                    'body' => '',
                    'button_label' => '',
                ],
            ],
        ])->assertRedirect(route('admin.home.edit'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Natural Indian Stones Supplier, Manufacturer and Exporter')
            ->assertDontSee('Stone from Jodhpur');
    }

    public function test_supplier_wording_rejects_html(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'supplier'), [
            'copy' => [
                'supplier' => [
                    'body' => '<script>alert(1)</script>',
                ],
            ],
        ])->assertSessionHasErrors([
            'copy.supplier.body' => 'Use plain text.',
        ]);

        $this->get('/')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_supplier_line_editor_shows_saved_wording_and_the_quote_page(): void
    {
        HomeSection::query()->create([
            'key' => 'supplier',
            'heading' => 'Stone from Jodhpur',
            'body' => 'Cut and packed in the workshop.',
            'button_label' => 'Ask for a price',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.home.edit'))
            ->assertOk()
            ->assertSee('Section content')
            ->assertSee('Live section preview')
            ->assertSee('value="Stone from Jodhpur"', false)
            ->assertSee('Cut and packed in the workshop.')
            ->assertSee('value="Ask for a price"', false)
            ->assertSee('name="copy[supplier][heading]"', false)
            ->assertSee('name="copy[supplier][body]"', false)
            ->assertSee('name="copy[supplier][button_label]"', false)
            ->assertSee('Quote page <span>/contact</span>', false)
            ->assertSee('Save collection')
            ->assertSee('Save stone cards')
            ->assertSee('Save journal')
            ->assertSee('Save closing band')
            ->assertDontSee('name="photo[supplier]', false)
            ->assertDontSee('OUR HERITAGE')
            ->assertDontSee('for projects and landscapes')
            ->assertDontSee('data-home-panel="supplier" data-open="1"', false);
    }

    public function test_rejected_supplier_wording_stays_on_the_supplier_editor(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('admin.home.edit'))
            ->followingRedirects()
            ->put(route('admin.home.update', 'supplier'), [
                'copy' => [
                    'supplier' => [
                        'heading' => 'Stone from Jodhpur',
                        'body' => '<b>Workshop notes</b>',
                        'button_label' => 'Ask for a price',
                    ],
                ],
            ])
            ->assertOk()
            ->assertSee('Use plain text.')
            ->assertSee('value="Stone from Jodhpur"', false)
            ->assertSee('value="Ask for a price"', false)
            ->assertSee('<b>Workshop notes</b>')
            ->assertSee('class="home-input home-supplier-paragraph is-invalid" id="supplier-body"', false)
            ->assertSee('data-home-panel="supplier" data-open="1"', false)
            ->assertDontSee('data-home-panel="hero" data-open="1"', false);

        $this->assertDatabaseCount('home_sections', 0);
    }

    public function test_opening_slide_words_appear_on_the_home_page(): void
    {
        $this->sandstone();

        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'hero'), [
            'title' => ['hero' => ['sandstone' => 'Warm stone.']],
            'subtitle' => ['hero' => ['sandstone' => 'Cut for your project.']],
            'link' => ['hero' => ['sandstone' => '/sandstone']],
        ])->assertRedirect(route('admin.home.edit'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Warm stone.')
            ->assertSee('Cut for your project.')
            ->assertSee('href="'.url('/sandstone').'"', false);
    }

    public function test_opening_slides_editor_binds_saved_copy_and_keeps_the_other_sections(): void
    {
        HomeImage::query()->create([
            'section' => 'hero',
            'slot' => 'limestone',
            'eyebrow' => 'Limestone from Kota',
            'title' => 'Quiet floors.',
            'body' => 'Honed limestone for courtyards.',
            'button_label' => 'See limestone',
            'link' => '/contact',
            'alt' => 'Kota limestone floor',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.home.edit'))
            ->assertOk()
            ->assertSee('Home page editor')
            ->assertSee('Slide library')
            ->assertSee('Save section')
            ->assertSee('Discard')
            ->assertSee('Link & Metadata')
            ->assertSee('Live homepage preview')
            ->assertSee('value="Quiet floors."', false)
            ->assertSee('value="Limestone from Kota"', false)
            ->assertSee('Honed limestone for courtyards.')
            ->assertSee('value="See limestone"', false)
            ->assertSee('value="/contact"', false)
            ->assertSee('value="Kota limestone floor"', false)
            ->assertSee('placeholder="Quiet stone for floors and steps."', false)
            ->assertSee('value="Indian natural stone."', false)
            ->assertSee('name="eyebrow[hero][limestone]"', false)
            ->assertSee('name="title[hero][limestone]"', false)
            ->assertSee('name="body[hero][limestone]"', false)
            ->assertSee('name="button_label[hero][limestone]"', false)
            ->assertSee('name="link[hero][limestone]"', false)
            ->assertSee('name="alt[hero][limestone]"', false)
            ->assertSee('name="photo[hero][limestone]"', false)
            ->assertSee('name="subtitle[hero][sandstone]"', false)
            ->assertDontSee('name="subtitle[hero][limestone]"', false)
            ->assertSee('data-home-panel="hero" data-open="1"', false)
            ->assertSee(route('admin.home.update', 'hero'), false)
            ->assertSee('name="copy[supplier][heading]"', false)
            ->assertSee('Live section preview')
            ->assertSee('Save collection')
            ->assertSee(route('admin.home.update', 'supplier'), false)
            ->assertDontSee('name="photo[supplier]', false)
            ->assertDontSee('data-home-panel="supplier" data-open="1"', false)
            ->assertDontSee('Add slide')
            ->assertDontSee('Drag to reorder')
            ->assertDontSee('Review changes');
    }

    public function test_the_opening_slide_editor_redisplays_a_rejected_page_on_the_link_tab(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('admin.home.edit'))
            ->followingRedirects()
            ->put(route('admin.home.update', 'hero'), [
                'title' => ['hero' => ['sandstone' => 'Warm stone.']],
                'eyebrow' => ['hero' => ['sandstone' => 'Jodhpur sandstone']],
                'link' => ['hero' => ['sandstone' => '/not-a-real-page']],
            ])
            ->assertOk()
            ->assertSee('Choose a page that already exists on the site.')
            ->assertSee('value="Warm stone."', false)
            ->assertSee('value="Jodhpur sandstone"', false)
            ->assertSee('value="/not-a-real-page"', false)
            ->assertSee('data-home-panel="hero" data-open="1"', false)
            ->assertSee('class="home-editor-tabpanel is-active" data-tab="metadata" id="home-sandstone-metadata"', false);
    }

    public function test_a_rejected_opening_slide_photograph_returns_to_the_image_tab(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('admin.home.edit'))
            ->followingRedirects()
            ->put(route('admin.home.update', 'hero'), [
                'photo' => [
                    'hero' => [
                        'granite' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
                    ],
                ],
            ])
            ->assertOk()
            ->assertSee('Use a JPG, PNG, or WebP image.')
            ->assertSee('class="home-editor-tabpanel is-active" data-tab="image" id="home-granite-image"', false);
    }

    public function test_replacing_an_opening_slide_photograph_shows_on_the_editor_and_the_home_page(): void
    {
        $admin = User::factory()->create();
        $path = null;

        try {
            $this->actingAs($admin)->put(route('admin.home.update', 'hero'), [
                'photo' => [
                    'hero' => [
                        'marble' => UploadedFile::fake()->image('marble.jpg', 1200, 800),
                    ],
                ],
                'title' => [
                    'hero' => [
                        'marble' => 'White marble.',
                    ],
                ],
            ])->assertRedirect(route('admin.home.edit'))->assertSessionHas('home_section', 'hero');

            $path = HomeImage::query()->where('section', 'hero')->where('slot', 'marble')->value('path');
            $this->assertIsString($path);
            $this->assertFileExists(public_path($path));

            $this->actingAs($admin)
                ->get(route('admin.home.edit'))
                ->assertOk()
                ->assertSee('White marble.')
                ->assertSee($path, false)
                ->assertSee('Use the original photograph');

            $this->get('/')
                ->assertOk()
                ->assertSee('White marble.')
                ->assertSee($path, false);
        } finally {
            $this->deleteUploaded(is_string($path) ? $path : null);
        }
    }

    public function test_an_opening_slide_link_must_be_an_existing_page(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'hero'), [
            'link' => ['hero' => ['sandstone' => '/not-a-real-page']],
        ])->assertSessionHasErrors([
            'link.hero.sandstone' => 'Choose a page that already exists on the site.',
        ]);
    }

    public function test_a_stone_card_name_can_be_changed(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.home.update', 'stones'), [
            'title' => ['stones' => ['index-stone-wall-panel' => 'Garden wall panels']],
            'link' => ['stones' => ['index-stone-wall-panel' => '/contact']],
        ])->assertRedirect(route('admin.home.edit'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Garden wall panels');

        $this->assertSame('/contact', HomeImage::query()->where('slot', 'index-stone-wall-panel')->value('link'));
    }

    private function sandstone(): void
    {
        Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'image' => 'assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg',
            'position' => 0,
        ]);
    }

    private function deleteUploaded(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $fullPath = public_path($path);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }

        $hash = substr(md5($path), 0, 16);
        foreach (glob(public_path('assets/img/optimized/'.$hash.'-*')) ?: [] as $variant) {
            if (is_file($variant)) {
                unlink($variant);
            }
        }
    }
}
