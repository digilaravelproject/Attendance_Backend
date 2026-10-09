<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_pages_are_available_at_their_public_urls(): void
    {
        $this->seed(PageSeeder::class);

        $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy');
        $this->get('/terms-condition')->assertOk()->assertSee('Terms &amp; Conditions', false);
        $this->get('/contact-us')->assertOk()->assertSee('Contact Us');
    }

    public function test_inactive_page_is_not_publicly_available(): void
    {
        Page::query()->create([
            'page_name' => 'Privacy Policy',
            'page_type' => 'privacy_policy',
            'description' => '<p>Hidden</p>',
            'status' => false,
        ]);

        $this->get('/privacy-policy')->assertNotFound();
    }
}
