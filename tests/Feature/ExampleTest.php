<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Indian natural stone.');
        $response->assertDontSee('data-bs-ride="carousel"', false);
        $response->assertSee('Tandur and Kota limestone');
        $response->assertSee('Absolute Black and other Indian granites');
        $response->assertDontSee('hero-carousel-pause', false);
        $response->assertSee('Natural Indian Stones Supplier, Manufacturer and Exporter');
        $response->assertSee('Shaped over millions of years');
        $response->assertSee('Stone Wall Panel');
        $response->assertSee('Natural Stone Step');
        $response->assertSee('Natural Stone Mosaic');
        $response->assertDontSee('A closer look');
        $response->assertDontSee('From selection to shipment');
        $response->assertDontSee('Stone in context');
    }
}
