<?php

namespace Tests\Feature;

use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelLobbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_opens_the_only_published_hotel(): void
    {
        Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        Hotel::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/')->assertRedirect('/demo');
    }

    public function test_home_lists_published_hotels_when_there_are_multiple(): void
    {
        Hotel::create(['name' => 'First Hotel', 'slug' => 'first', 'public_status' => 'published']);
        Hotel::create(['name' => 'Second Hotel', 'slug' => 'second', 'public_status' => 'published']);
        Hotel::create(['name' => 'Hidden Hotel', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Hotel')->assertSee('Second Hotel')->assertDontSee('Hidden Hotel');
    }

    public function test_lobby_shows_only_active_facilities_for_the_current_hotel(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true]);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);
        $other = Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Other pool', 'body' => 'Other hotel.', 'is_active' => true]);

        $this->get('/demo')->assertOk()->assertSee('Garden pool')->assertDontSee('Hidden spa')->assertDontSee('Other pool');
        $this->get('/demo?lang=en')->assertOk()->assertSee('Welcome to your')->assertSee('virtual lobby');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('バーチャルロビー');
    }

    public function test_unpublished_lobby_is_not_accessible(): void
    {
        Hotel::create(['name' => 'Draft', 'slug' => 'draft']);
        $this->get('/draft')->assertNotFound();
    }
}
