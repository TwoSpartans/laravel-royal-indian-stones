<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enquiry;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_category_and_product_pages_use_existing_slugs(): void
    {
        $category = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
            'description' => 'Indian sandstone for paving and cladding.',
            'position' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Jodhpur Pink Sandstone',
            'slug' => 'sandstone-jodhpur-pink',
            'color' => 'Pink',
            'finishes' => ['Honed'],
            'applications' => ['Paving'],
            'summary' => 'A pink sandstone from Jodhpur.',
            'detail' => 'Used for paving and wall cladding.',
            'application' => 'Flooring and paving.',
        ]);
        $product->specs()->create(['label' => 'Origin', 'value' => 'India', 'position' => 0]);

        $this->get('/products')->assertOk()->assertSee('Find the stone for your project');
        $this->get('/sandstone')->assertOk()->assertSee('Jodhpur Pink Sandstone');
        $this->get('/sandstone-jodhpur-pink')->assertOk()->assertSee('Request a Quote for This Stone');
        $this->get('/sandstone-jodhpur-pink.html')->assertRedirect('/sandstone-jodhpur-pink');
        $this->get('/contact?product=sandstone-jodhpur-pink')->assertOk()->assertSee('Quotation: Jodhpur Pink Sandstone');
        $this->get('/aboutus')->assertNotFound();
    }

    public function test_enquiry_is_stored_and_keeps_quote_fields(): void
    {
        $payload = [
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'subject' => 'Natural stone quotation',
            'message' => 'We need pink sandstone tiles for a courtyard.',
            'product' => 'Jodhpur Pink Sandstone',
            'quantity' => '200 m2',
            'dimensions' => '60 x 60 cm',
            'finish' => 'Honed',
            'destination' => 'United Kingdom',
        ];

        $this->postJson('/contact', $payload)
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Your enquiry has been received. Thank you for sharing your requirements.',
            ]);

        $this->assertDatabaseHas('enquiries', [
            'email' => 'asha@example.com',
            'product' => 'Jodhpur Pink Sandstone',
            'quantity' => '200 m2',
            'destination' => 'United Kingdom',
        ]);
        $this->assertSame(1, Enquiry::query()->count());
    }

    public function test_enquiry_rejects_a_short_message(): void
    {
        $this->postJson('/contact', [
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'subject' => 'Natural stone quotation',
            'message' => 'Too short',
        ])->assertStatus(422)->assertJson([
            'ok' => false,
            'message' => 'Please describe your requirements in at least 10 characters.',
        ]);

        $this->assertSame(0, Enquiry::query()->count());
    }

    public function test_newsletter_subscription_is_stored(): void
    {
        $this->postJson('/newsletter', ['email_address' => 'buyer@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue(NewsletterSubscriber::query()->where('email', 'buyer@example.com')->exists());
    }
}
