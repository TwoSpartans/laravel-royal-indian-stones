<?php

namespace Tests\Feature;

use App\Models\Catalogue;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ECatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_opens_a_simple_e_catalogue(): void
    {
        Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'description' => 'Indian sandstone for paving and cladding.',
            'position' => 1,
        ]);
        Category::query()->create([
            'name' => 'Stone Articles',
            'slug' => 'stone-articles',
            'description' => 'Cut articles.',
            'position' => 2,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('e-catalogue').'"', false)
            ->assertSee('>E-catalogue</a>', false)
            ->assertDontSee('Staff portal')
            ->assertDontSee(route('portal.login'), false);

        $this->get(route('e-catalogue'))
            ->assertOk()
            ->assertSee('<title>E-catalogue | Royal Indian Stones</title>', false)
            ->assertSee('Sandstone')
            ->assertSee('Indian sandstone for paving and cladding.')
            ->assertSee('href="'.url('/sandstone').'"', false)
            ->assertDontSee('Cut articles.');

        $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-pages.xml', false);
        $this->get('/sitemap-pages.xml')->assertOk()->assertSee('/e-catalogue', false);
    }

    public function test_the_e_catalogue_explains_when_no_collections_are_listed(): void
    {
        $this->get('/e-catalogue')
            ->assertOk()
            ->assertSee('The catalogue is being updated.');
    }

    public function test_materials_are_ordered_with_their_cover_introduction_and_published_count(): void
    {
        $sandstone = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'description' => 'A shorter sandstone line.',
            'introduction' => 'Sandstone cut for floors and courtyards.',
            'image' => 'assets/img/products/sandstone/cover.jpg',
            'position' => 2,
        ]);
        Category::query()->create([
            'name' => 'Marble',
            'slug' => 'marble',
            'description' => 'Marble from the Indian quarries.',
            'position' => 0,
        ]);
        $sandstone->products()->create([
            'name' => 'Jodhpur Pink',
            'slug' => 'jodhpur-pink',
            'color' => '',
            'finishes' => [],
            'applications' => [],
            'summary' => '',
            'is_published' => true,
        ]);
        $sandstone->products()->create([
            'name' => 'Hidden Pink',
            'slug' => 'hidden-pink',
            'color' => '',
            'finishes' => [],
            'applications' => [],
            'summary' => '',
            'is_published' => false,
        ]);

        $this->get('/e-catalogue')
            ->assertOk()
            ->assertSeeInOrder(['Marble', 'Sandstone'])
            ->assertSee('Marble from the Indian quarries.')
            ->assertSee('Sandstone cut for floors and courtyards.')
            ->assertDontSee('A shorter sandstone line.')
            ->assertSee('assets/img/products/sandstone/cover.jpg', false)
            ->assertSee('e-catalogue-placeholder', false)
            ->assertSee('1 product')
            ->assertSee('0 products')
            ->assertSee('View collection')
            ->assertSee('href="'.url('/marble').'"', false)
            ->assertDontSee('Download PDF');
    }

    public function test_a_saved_catalogue_can_be_previewed_before_download(): void
    {
        $directory = storage_path('app/public/catalogue/range-2026');
        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.'catalogue-test.pdf', '%PDF-1.4 test');

        try {
            $catalogue = Catalogue::query()->create([
                'title' => 'Range 2026',
                'slug' => 'range-2026',
                'description' => 'Sandstone, marble, and granite in the current range.',
                'version_label' => 'Spring edition',
                'pdf_path' => 'storage/catalogue/range-2026/catalogue-test.pdf',
                'pdf_original_name' => 'range-2026.pdf',
                'position' => 0,
            ]);

            $this->get('/e-catalogue')
                ->assertOk()
                ->assertSee('Range 2026')
                ->assertDontSee('Spring edition')
                ->assertSee('Sandstone, marble, and granite in the current range.')
                ->assertSee('href="'.route('e-catalogue.preview', ['catalogue' => $catalogue->slug]).'"', false)
                ->assertSee('data-catalogue-preview', false)
                ->assertSee('id="cataloguePreviewModal"', false);

            $this->get('/e-catalogue/preview/range-2026?embed=1')
                ->assertOk()
                ->assertSee('Range 2026')
                ->assertDontSee('Spring edition')
                ->assertSee('Sandstone, marble, and granite in the current range.')
                ->assertSee('Download PDF')
                ->assertSee('src="'.asset($catalogue->pdf_path).'"', false)
                ->assertDontSee('Back to e-catalogue')
                ->assertDontSee('<title>Range 2026 | E-catalogue | Royal Indian Stones</title>', false);

            $this->get('/e-catalogue/preview/range-2026')
                ->assertOk()
                ->assertSee('<title>Range 2026 | E-catalogue | Royal Indian Stones</title>', false)
                ->assertDontSee('Spring edition')
                ->assertSee('Sandstone, marble, and granite in the current range.')
                ->assertSee('Download PDF')
                ->assertSee('Back to e-catalogue')
                ->assertSee('href="'.route('e-catalogue').'"', false)
                ->assertSee('src="'.asset($catalogue->pdf_path).'"', false);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
