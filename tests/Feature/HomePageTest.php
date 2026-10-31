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
            $this->assertFileExists($this->storedUpload($first));

            $this->get('/')
                ->assertOk()
                ->assertSee($first, false)
                ->assertDontSee('/assets/img/optimized/'.substr(md5($first), 0, 16), false)
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
            $this->assertFileDoesNotExist($this->storedUpload($first));
            $this->assertFileExists($this->storedUpload($second));

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
            $this->assertFileDoesNotExist($this->storedUpload($path));
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
        $this->assertSame([], glob(public_path('assets/img/home/explore/not-a-real-stone*')) ?: []);
        $this->assertSame([], glob(storage_path('app/public/home/explore/not-a-real-stone*')) ?: []);
    }

    public function test_a_homepage_photograph_stored_in_the_application_folder_is_copied_where_the_live_site_can_serve_it(): void
    {
        $this->sandstone();
        $legacy = 'assets/img/home/explore/sandstone-legacy.jpg';
        $source = public_path('assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg');
        $this->assertFileExists($source);
        if (! is_dir(dirname(public_path($legacy)))) {
            mkdir(dirname(public_path($legacy)), 0777, true);
        }
        copy($source, public_path($legacy));
        HomeImage::query()->create([
            'section' => 'explore',
            'slot' => 'sandstone',
            'path' => $legacy,
            'alt' => 'Warm sandstone',
        ]);

        try {
            $this->get('/')
                ->assertOk()
                ->assertSee('storage/home/explore/sandstone-legacy.jpg', false)
                ->assertDontSee($legacy, false);

            $this->assertSame('storage/home/explore/sandstone-legacy.jpg', HomeImage::query()->where('slot', 'sandstone')->value('path'));
            $this->assertFileExists(storage_path('app/public/home/explore/sandstone-legacy.jpg'));
        } finally {
            $this->deleteUploaded('storage/home/explore/sandstone-legacy.jpg');
            if (is_file(public_path($legacy))) {
                unlink(public_path($legacy));
            }
        }
    }

    public function test_explore_editor_shows_catalogue_cards_in_catalogue_order(): void
    {
        $catalogue = [
            ['Sandstone', 'sandstone'],
            ['Limestone', 'limestone'],
            ['Marble', 'marble'],
            ['Granite', 'granite'],
            ['Slate', 'slate'],
            ['Quartzite', 'quartzite'],
            ['Pebbles', 'stone-pebbles'],
            ['Cobbles', 'stone-cobbles'],
            ['Stone chips', 'stone-chips'],
            ['Basalt', 'stone-basalt'],
            ['Soapstone', 'stone-soap'],
            ['Travertine', 'stone-travertine'],
        ];
        foreach ($catalogue as $position => [$name, $slug]) {
            Category::query()->create([
                'name' => $name,
                'slug' => $slug,
                'image' => 'assets/img/catalogue/'.$slug.'.jpg',
                'position' => $position,
            ]);
        }
        Category::query()->create([
            'name' => 'Stone articles',
            'slug' => 'stone-articles',
            'position' => 20,
        ]);
        Category::query()->create([
            'name' => 'Stone applications',
            'slug' => 'stone-applications',
            'position' => 21,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.home.edit'))
            ->assertOk()
            ->assertSee('Explore collection')
            ->assertSee('value="Explore the collection"', false)
            ->assertSee('Sandstone, limestone, marble, granite, slate and the other stones we cut in India, chosen for floors, walls, roofs and gardens.')
            ->assertSee('name="copy[explore][heading]"', false)
            ->assertSee('name="copy[explore][body]"', false)
            ->assertSee('12 category cards')
            ->assertSee('12 total')
            ->assertSee('Category cards')
            ->assertSee('Homepage description')
            ->assertSee('Catalogue destination')
            ->assertSee('name="alt[explore][stone-soap]"', false)
            ->assertSee('name="photo[explore][stone-soap]"', false)
            ->assertSeeInOrder([
                'data-edit-card="sandstone"',
                'data-edit-card="limestone"',
                'data-edit-card="marble"',
                'data-edit-card="granite"',
                'data-edit-card="slate"',
                'data-edit-card="quartzite"',
                'data-edit-card="stone-pebbles"',
                'data-edit-card="stone-cobbles"',
                'data-edit-card="stone-chips"',
                'data-edit-card="stone-basalt"',
                'data-edit-card="stone-soap"',
                'data-edit-card="stone-travertine"',
            ], false)
            ->assertDontSee('data-edit-card="stone-articles"', false)
            ->assertDontSee('data-edit-card="stone-applications"', false)
            ->assertDontSee('name="title[explore]', false)
            ->assertDontSee('name="link[explore]', false)
            ->assertDontSee('Add card')
            ->assertDontSee('Reorder cards')
            ->assertDontSee('Bulk image replace');
    }

    public function test_replacing_an_explore_photograph_leaves_the_catalogue_record_unchanged(): void
    {
        $this->sandstone();
        $limestoneImage = 'assets/img/products/limestone/n-green/n-green-limestone-natural-finish-decorative-wall-tiles.jpg';
        Category::query()->create([
            'name' => 'Limestone',
            'slug' => 'limestone',
            'image' => $limestoneImage,
            'position' => 1,
        ]);
        $admin = User::factory()->create();
        $catalogueImage = 'assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg';
        $path = null;

        try {
            $this->actingAs($admin)->put(route('admin.home.update', 'explore'), [
                'copy' => [
                    'explore' => [
                        'heading' => 'Stones for the courtyard',
                        'body' => 'Cut in India for floors and walls.',
                    ],
                ],
                'alt' => [
                    'explore' => [
                        'sandstone' => 'Warm Agra sandstone',
                    ],
                ],
                'photo' => [
                    'explore' => [
                        'sandstone' => UploadedFile::fake()->image('cover.jpg', 800, 500),
                    ],
                ],
            ])->assertRedirect(route('admin.home.edit'))->assertSessionHas('home_section', 'explore');

            $path = HomeImage::query()->where('section', 'explore')->where('slot', 'sandstone')->value('path');
            $this->assertIsString($path);
            $this->assertStringStartsWith('storage/home/explore/', $path);
            $this->assertNotSame($catalogueImage, $path);
            $this->assertFileExists($this->storedUpload($path));
            $this->assertSame($catalogueImage, Category::query()->where('slug', 'sandstone')->value('image'));
            $this->assertSame('Sandstone', Category::query()->where('slug', 'sandstone')->value('name'));
            $this->assertSame($limestoneImage, Category::query()->where('slug', 'limestone')->value('image'));
            $this->assertSame('Limestone', Category::query()->where('slug', 'limestone')->value('name'));
            $this->assertNull(HomeImage::query()->where('section', 'explore')->where('slot', 'limestone')->first());

            $this->get('/')
                ->assertOk()
                ->assertSee('Stones for the courtyard')
                ->assertSee('Cut in India for floors and walls.')
                ->assertSee('Warm Agra sandstone')
                ->assertSee($path, false)
                ->assertDontSee($catalogueImage, false);

            $this->actingAs($admin)
                ->get(route('admin.home.edit'))
                ->assertOk()
                ->assertSee('value="Stones for the courtyard"', false)
                ->assertSee('Warm Agra sandstone')
                ->assertSee('Homepage photograph')
                ->assertSee($path, false)
                ->assertSee($catalogueImage, false)
                ->assertSee('Use the original photograph')
                ->assertSee('/sandstone', false);
        } finally {
            $this->deleteUploaded(is_string($path) ? $path : null);
        }
    }

    public function test_a_rejected_explore_photograph_returns_to_that_category(): void
    {
        $this->sandstone();
        $catalogueImage = 'assets/img/products/sandstone/agra-red/agra-red-sandstone-natural-mosaic-tiles.jpg';

        $this->actingAs(User::factory()->create())
            ->from(route('admin.home.edit'))
            ->followingRedirects()
            ->put(route('admin.home.update', 'explore'), [
                'copy' => [
                    'explore' => [
                        'heading' => 'Stones for the courtyard',
                        'body' => 'Cut in India for floors and walls.',
                    ],
                ],
                'alt' => [
                    'explore' => [
                        'sandstone' => 'Warm Agra sandstone',
                    ],
                ],
                'photo' => [
                    'explore' => [
                        'sandstone' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
                    ],
                ],
            ])
            ->assertOk()
            ->assertSee('Use a JPG, PNG, or WebP image.')
            ->assertSee('data-open-slot="sandstone"', false)
            ->assertSee('data-home-panel="explore" data-open="1"', false)
            ->assertSee('value="Stones for the courtyard"', false)
            ->assertSee('Cut in India for floors and walls.')
            ->assertSee('Warm Agra sandstone')
            ->assertSee('class="home-file-input is-invalid" id="photo-explore-sandstone"', false)
            ->assertDontSee('data-home-panel="hero" data-open="1"', false);

        $this->assertDatabaseCount('home_images', 0);
        $this->assertDatabaseCount('home_sections', 0);
        $this->assertSame($catalogueImage, Category::query()->where('slug', 'sandstone')->value('image'));
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
                ],
            ],
        ])->assertRedirect(route('admin.home.edit'))->assertSessionHas('home_section', 'supplier');

        $this->get('/')
            ->assertOk()
            ->assertSee('Stone from Jodhpur')
            ->assertSee('Cut and packed in the workshop.')
            ->assertDontSee('supplier-quote', false)
            ->assertDontSee('ris-button-light', false);

        $this->actingAs($admin)->put(route('admin.home.update', 'supplier'), [
            'copy' => [
                'supplier' => [
                    'heading' => '',
                    'body' => '',
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

    public function test_supplier_line_editor_shows_saved_wording_without_a_quote_button(): void
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
            ->assertSee('name="copy[supplier][heading]"', false)
            ->assertSee('name="copy[supplier][body]"', false)
            ->assertDontSee('name="copy[supplier][button_label]"', false)
            ->assertDontSee('Quote page', false)
            ->assertDontSee('home-preview-quote', false)
            ->assertDontSee('Request a Quote stays on every slide')
            ->assertSee('name="copy[explore][heading]"', false)
            ->assertSee('name="title[stones][index-stone-wall-panel]"', false)
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
                    ],
                ],
            ])
            ->assertOk()
            ->assertSee('Use plain text.')
            ->assertSee('value="Stone from Jodhpur"', false)
            ->assertDontSee('name="copy[supplier][button_label]"', false)
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
            ->assertSee('href="'.url('/sandstone').'"', false)
            ->assertDontSee('ris-button-light', false)
            ->assertDontSee('supplier-quote', false);
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
            ->assertSee('Preview sizes')
            ->assertDontSee('Link & Metadata')
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
            ->assertSee('name="copy[explore][heading]"', false)
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
            ->assertSee('class="home-editor-tabpanel is-active" data-tab="content" id="home-sandstone-content"', false)
            ->assertDontSee('data-tab="metadata"', false);
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
            $this->assertFileExists($this->storedUpload($path));

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

    public function test_stone_editor_lists_application_cards_in_definition_order(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.home.edit'));

        $response->assertOk()
            ->assertSee('Stone application cards')
            ->assertSee('14 total')
            ->assertSee('Search by name or page')
            ->assertSee('name="copy[stones][heading]"', false)
            ->assertSee('name="copy[stones][body]"', false)
            ->assertSee('name="title[stones][index-stone-wall-panel]"', false)
            ->assertSee('name="link[stones][index-stone-wall-panel]"', false)
            ->assertSee('name="alt[stones][index-stone-mosaic]"', false)
            ->assertSee('name="photo[stones][index-stone-wall-panel]"', false)
            ->assertSee('kandla-grey-sandstone-wall-panels.jpg', false)
            ->assertSee('The destination page must already exist before assigning this card.')
            ->assertSee('Shaped over millions of years, natural Indian stone brings distinct textures to walls, pathways, roofs and architectural details.', false)
            ->assertDontSee('Add item')
            ->assertDontSee('Grid view');

        $content = $response->getContent();
        $panel = strpos($content, 'data-stone-row="index-stone-wall-panel"');
        $cladding = strpos($content, 'data-stone-row="index-stone-wall-cladding"');
        $mosaic = strpos($content, 'data-stone-row="index-stone-mosaic"');
        $this->assertNotFalse($panel);
        $this->assertNotFalse($cladding);
        $this->assertNotFalse($mosaic);
        $this->assertLessThan($cladding, $panel);
        $this->assertLessThan($mosaic, $cladding);
    }

    public function test_a_missing_stone_page_is_rejected_and_the_card_stays_open(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.home.edit'))
            ->followingRedirects()
            ->put(route('admin.home.update', 'stones'), [
                'title' => ['stones' => ['index-stone-kerb' => 'Garden kerb']],
                'link' => ['stones' => ['index-stone-kerb' => '/not-a-real-page']],
            ])
            ->assertOk()
            ->assertSee('Choose a page that already exists on the site.')
            ->assertSee('data-home-panel="stones" data-open="1" data-open-slot="index-stone-kerb"', false)
            ->assertSee('value="Garden kerb"', false)
            ->assertSee('value="/not-a-real-page"', false)
            ->assertSee('id="link-stones-index-stone-kerb"', false)
            ->assertSee('is-invalid', false);

        $this->assertSame(0, HomeImage::query()->where('section', 'stones')->count());
        $this->assertSame(0, HomeSection::query()->where('key', 'stones')->count());
    }

    public function test_replacing_a_stone_photograph_leaves_the_original_file_in_place(): void
    {
        $admin = User::factory()->create();
        $original = 'assets/img/products/index/wall-panel/kandla-grey-sandstone-wall-panels.jpg';
        $this->assertFileExists(public_path($original));
        $path = null;

        try {
            $this->actingAs($admin)->put(route('admin.home.update', 'stones'), [
                'photo' => [
                    'stones' => [
                        'index-stone-wall-panel' => UploadedFile::fake()->image('panel.jpg', 800, 500),
                    ],
                ],
                'alt' => ['stones' => ['index-stone-wall-panel' => 'Grey wall panels']],
            ])->assertRedirect(route('admin.home.edit'));

            $path = HomeImage::query()->where('section', 'stones')->where('slot', 'index-stone-wall-panel')->value('path');
            $this->assertIsString($path);
            $this->assertStringStartsWith('storage/home/stones/', $path);
            $this->assertNotSame($original, $path);
            $this->assertFileExists($this->storedUpload($path));
            $this->assertFileExists(public_path($original));
            $this->assertSame(1, HomeImage::query()->where('section', 'stones')->count());

            $this->get('/')
                ->assertOk()
                ->assertSee('Grey wall panels')
                ->assertSee($path, false)
                ->assertDontSee('/assets/img/optimized/'.substr(md5((string) $path), 0, 16), false)
                ->assertSee('Stone Wall Cladding');

            $this->actingAs($admin)
                ->get(route('admin.home.edit'))
                ->assertOk()
                ->assertSee('Homepage photograph')
                ->assertSee($path, false)
                ->assertSee('Use the original photograph')
                ->assertSee('value="Grey wall panels"', false);
        } finally {
            $this->deleteUploaded(is_string($path) ? $path : null);
        }
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

    private function storedUpload(?string $path): string
    {
        $path = str_replace('\\', '/', (string) $path);
        if (str_starts_with($path, 'storage/')) {
            return storage_path('app/public/'.substr($path, strlen('storage/')));
        }

        return public_path($path);
    }

    private function deleteUploaded(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $fullPath = $this->storedUpload($path);
        if (is_file($fullPath)) {
            unlink($fullPath);
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
    }
}
