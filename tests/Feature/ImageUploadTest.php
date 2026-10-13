<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->owner = User::factory()->create();
        $this->hotel->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function roomForm(array $override = []): array
    {
        return ['name' => 'Deluxe', 'max_adults' => 2, 'max_children' => 0, 'base_price' => 100, 'is_active' => 1, ...$override];
    }

    public function test_an_uploaded_room_photo_is_shrunk_stored_and_shown_next_to_url_photos(): void
    {
        $this->actingAs($this->owner)->post(route('admin.room-types.store'), $this->roomForm(['new_images' => [
            ['file' => UploadedFile::fake()->image('bed.jpg', 3200, 2000), 'tags' => 'bedroom', 'alt' => 'The bed'],
            ['url' => 'https://example.test/view.jpg', 'tags' => 'view'],
        ]]))->assertSessionHasNoErrors();

        $images = $this->hotel->roomTypes()->firstOrFail()->images;
        $this->assertCount(2, $images);

        $uploaded = $images->firstWhere('image_url', null);
        Storage::disk('public')->assertExists($uploaded->image_path);
        $this->assertStringEndsWith('.webp', $uploaded->image_path);
        $this->assertLessThanOrEqual(1600, getimagesizefromstring(Storage::disk('public')->get($uploaded->image_path))[0]);
        $this->assertStringContainsString($uploaded->image_path, $uploaded->image_source);
        $this->assertSame('https://example.test/view.jpg', $images->firstWhere('image_url', '!=', null)->image_source);
    }

    public function test_deleting_a_room_photo_removes_its_file(): void
    {
        $this->actingAs($this->owner)->post(route('admin.room-types.store'), $this->roomForm(['new_images' => [['file' => UploadedFile::fake()->image('a.png', 400, 300)]]]));
        $room = $this->hotel->roomTypes()->firstOrFail();
        $image = $room->images()->firstOrFail();

        $this->actingAs($this->owner)->put(route('admin.room-types.update', $room), $this->roomForm(['delete_images' => [$image->id]]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($image->image_path);
        $this->assertSame(0, $room->images()->count());
    }

    public function test_files_that_are_not_pictures_are_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.room-types.store'), $this->roomForm(['new_images' => [['file' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php')]]]))
            ->assertSessionHasErrors('new_images.0.file');

        $this->assertSame(0, $this->hotel->roomTypes()->count());
    }

    public function test_a_facility_can_use_an_uploaded_cover_and_gallery_together_with_urls(): void
    {
        $this->actingAs($this->owner)->post(route('admin.knowledge-items.store'), [
            'category' => 'facilities', 'title' => 'Pool', 'body' => 'Open daily.', 'is_active' => 1,
            'image_file' => UploadedFile::fake()->image('pool.jpg', 2400, 1600),
            'gallery' => 'https://example.test/one.jpg',
            'gallery_files' => [UploadedFile::fake()->image('two.png', 800, 600)],
        ])->assertSessionHasNoErrors();

        $item = $this->hotel->knowledgeItems()->firstOrFail();

        $this->assertStringContainsString('/storage/knowledge/', $item->image_url);
        $this->assertCount(2, $item->images);
        $this->assertSame('https://example.test/one.jpg', $item->images[0]);
        $this->assertCount(3, $item->photoUrls());
        $this->assertCount(2, Storage::disk('public')->files('knowledge'));
    }
}
