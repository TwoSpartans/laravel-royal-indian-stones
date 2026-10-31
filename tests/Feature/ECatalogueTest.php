<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get('/sitemap.xml')->assertOk()->assertSee('/e-catalogue', false);
    }

    public function test_the_e_catalogue_explains_when_no_collections_are_listed(): void
    {
        $this->get('/e-catalogue')
            ->assertOk()
            ->assertSee('The catalogue is being updated.');
    }
}
