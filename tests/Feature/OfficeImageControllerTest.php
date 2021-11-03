<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function itUploadsOfficeImage()
    {
        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $this->actingAs($user);

        $image = UploadedFile::fake()->image('image.png');

        $response = $this->postJson("/api/offices/{$office->id}/images", [
            'image' =>$image
        ]);

        $response->assertCreated();
        Storage::disk('public')->assertExists($response->json('data.path'));
        Storage::disk('public')->delete($response->json('data.path'));
        Storage::disk('public')->assertMissing($response->json('data.path'));
    }

    /** @test */
    public function itDeletesOfficeImage()
    {
        Storage::disk('public')->put('uploaded_image.png', 'empty');
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $this->actingAs($user);

        $office->images()->create([
            'path' => 'uploaded_image.png'
        ]);

        $image = $office->images()->create([
            'path' => 'uploaded_image.png'
        ]);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");
        $response->assertOk();
        $this->assertModelMissing($image);

        Storage::disk('public')->delete($image->path);
        Storage::disk('public')->assertMissing($image->path);
    }

    /** @test */
    public function itCannotDeleteTheOnlyOfficeImage()
    {
        Storage::disk('public')->put('uploaded_image.png', 'empty');
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $this->actingAs($user);

        $image = $office->images()->create([
            'path' => 'uploaded_image.png'
        ]);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertJsonValidationErrors(['image' => 'Cannot delete the only image.']);
        $response->assertUnprocessable();
        Storage::disk('public')->delete($image->path);
        Storage::disk('public')->assertMissing($image->path);
    }

    /** @test */
    public function itCannotDeleteTheOfficeFeaturedImage()
    {
        Storage::disk('public')->put('uploaded_image.png', 'empty');
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $this->actingAs($user);

        $office->images()->create([
            'path' => 'uploaded_image.png'
        ]);

        $image = $office->images()->create([
            'path' => 'uploaded_image.png'
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertJsonValidationErrors(['image' => 'Cannot delete the featured image.']);
        $response->assertUnprocessable();

        Storage::disk('public')->delete($image->path);
        Storage::disk('public')->assertMissing($image->path);
    }
}
