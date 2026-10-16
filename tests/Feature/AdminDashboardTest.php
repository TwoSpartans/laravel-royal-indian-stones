<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Enquiry;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }

    public function test_dashboard_shows_catalogue_counts_and_recent_work(): void
    {
        $admin = User::factory()->create([
            'name' => 'Bharat Singh',
            'email' => 'bharat@example.com',
        ]);
        $category = Category::query()->create([
            'name' => 'Sandstone',
            'slug' => 'sandstone',
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Jodhpur Pink',
            'slug' => 'jodhpur-pink',
            'finishes' => ['honed'],
            'applications' => ['flooring'],
        ]);
        Enquiry::query()->create([
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'subject' => 'Natural stone quotation',
            'message' => 'Courtyard paving in pink sandstone.',
        ]);
        NewsletterSubscriber::query()->create(['email' => 'buyer@example.com']);
        $post = Post::query()->create([
            'slug' => 'indian-black-basalt',
            'title' => 'Indian Black Basalt',
            'summary' => 'A note on black basalt.',
            'body' => '<p>Basalt from the Deccan Plateau.</p>',
            'published_on' => '2024-01-22',
        ]);
        Comment::query()->create([
            'post_id' => $post->id,
            'name' => 'Ravi',
            'email' => 'ravi@example.com',
            'body' => 'Waiting for approval.',
            'approved' => false,
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Admin Panel')
            ->assertSee('Dashboard')
            ->assertSee('Overview of the catalogue.')
            ->assertSee('Asha Sharma')
            ->assertSee('Indian Black Basalt')
            ->assertSee('Bharat Singh')
            ->assertSee('bharat@example.com')
            ->assertSee('admin-count">1', false);

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/dashboard');
    }

    public function test_sign_in_opens_the_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($admin);
    }
}
