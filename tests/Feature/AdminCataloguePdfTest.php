<?php

namespace Tests\Feature;

use App\Models\Catalogue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminCataloguePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['export-range', 'archive'] as $slug) {
            File::deleteDirectory(storage_path('app/public/catalogue/'.$slug));
        }

        parent::tearDown();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin/catalogues')->assertRedirect('/admin/login');
        $this->post('/admin/catalogues')->assertRedirect('/admin/login');
        $this->get('/admin/catalogue-pdf')->assertRedirect('/admin/login');
    }

    public function test_a_signed_in_visitor_cannot_open_catalogues(): void
    {
        $visitor = User::factory()->create();
        $visitor->forceFill(['is_site_admin' => false])->save();

        $this->actingAs($visitor)->get('/admin/catalogues')->assertForbidden();
    }

    public function test_an_admin_can_add_reorder_replace_and_delete_a_catalogue(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/catalogue-pdf')->assertRedirect('/admin/catalogues');

        $this->actingAs($admin)
            ->post('/admin/catalogues', [
                'title' => 'Export range',
                'description' => 'The stones we currently export.',
                'version_label' => '2026',
                'pdf' => UploadedFile::fake()->create('stones.pdf', 120, 'application/pdf'),
                'cover' => UploadedFile::fake()->image('cover.jpg', 640, 800),
            ])
            ->assertRedirect();

        $first = Catalogue::query()->where('slug', 'export-range')->first();
        $this->assertNotNull($first);
        $this->assertSame('stones.pdf', $first->pdf_original_name);
        $this->assertSame('2026', $first->version_label);
        $this->assertNotNull($first->cover_path);
        $firstPdf = (string) $first->pdf_path;
        $this->assertFileExists($this->absolute($firstPdf));

        $this->actingAs($admin)->post('/admin/catalogues', [
            'title' => 'Archive',
            'pdf' => UploadedFile::fake()->create('archive.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $archive = Catalogue::query()->where('slug', 'archive')->firstOrFail();
        $this->actingAs($admin)->post('/admin/catalogues/reorder', [
            'id' => $archive->id,
            'direction' => 'up',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(
            [$archive->id, $first->id],
            Catalogue::query()->orderBy('position')->pluck('id')->all(),
        );

        $this->get('/e-catalogue')
            ->assertOk()
            ->assertSeeInOrder(['Archive', 'Export range'])
            ->assertSee('The stones we currently export.')
            ->assertSee('Preview')
            ->assertSee('Download PDF')
            ->assertSee('href="'.route('e-catalogue.preview', ['catalogue' => $first->slug]).'"', false);

        $this->actingAs($admin)->put('/admin/catalogues/'.$first->id, [
            'title' => 'Export range',
            'slug' => 'export-range',
            'description' => 'Updated export notes.',
            'version_label' => '2026',
            'pdf' => UploadedFile::fake()->create('updated-range.pdf', 80, 'application/pdf'),
        ])->assertRedirect('/admin/catalogues/'.$first->id.'/edit');

        $first->refresh();
        $this->assertSame('updated-range.pdf', $first->pdf_original_name);
        $this->assertNotSame($firstPdf, $first->pdf_path);
        $this->assertFileDoesNotExist($this->absolute($firstPdf));
        $this->assertFileExists($this->absolute((string) $first->pdf_path));

        $this->actingAs($admin)
            ->delete('/admin/catalogues/'.$first->id)
            ->assertRedirect('/admin/catalogues')
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('catalogues', ['id' => $first->id]);
        $this->assertFileDoesNotExist($this->absolute((string) $first->pdf_path));
        $this->get('/e-catalogue/preview/export-range')->assertNotFound();
    }

    public function test_a_non_pdf_upload_is_rejected(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from('/admin/catalogues/create')
            ->post('/admin/catalogues', [
                'title' => 'Broken upload',
                'pdf' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertRedirect('/admin/catalogues/create')
            ->assertSessionHasErrors('pdf');

        $this->assertSame(0, Catalogue::query()->count());
    }

    public function test_a_catalogue_without_a_pdf_stays_off_the_public_page(): void
    {
        Catalogue::query()->create([
            'title' => 'Draft catalogue',
            'slug' => 'draft-catalogue',
            'description' => 'Not ready.',
            'position' => 0,
        ]);

        $this->get('/e-catalogue')
            ->assertOk()
            ->assertDontSee('Draft catalogue')
            ->assertDontSee('Download PDF');

        $this->get('/e-catalogue/preview/draft-catalogue')->assertNotFound();
    }

    private function absolute(string $path): string
    {
        return storage_path('app/public/'.substr($path, strlen('storage/')));
    }
}
