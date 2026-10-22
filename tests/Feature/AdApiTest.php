<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guests_can_only_see_active_ads()
    {
        Ad::truncate();
        Ad::factory()->create(['status' => 'active']);
        Ad::factory()->create(['status' => 'paused']);

        $response = $this->getJson('/api/ads/active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('ads'));
    }

    /** @test */
    public function guests_can_view_single_ad()
    {
        $ad = Ad::factory()->create(['status' => 'active']);

        $response = $this->getJson("/api/ads/{$ad->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $ad->id]);
    }

    /** @test */
    public function guests_cannot_access_admin_ads()
    {
        $this->getJson('/api/ads')->assertStatus(401);
    }

    /** @test */
    public function non_admin_users_cannot_manage_ads()
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads', [
                'title' => 'Hacked',
                'content' => 'Nope',
                'discount_type' => 'percentage',
                'status' => 'active'
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_ad_with_image()
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $file = UploadedFile::fake()->image('ad.jpg');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/ads', [
                'title' => 'Admin Ad',
                'content' => 'Valid ad',
                'discount' => 10,
                'discount_type' => 'percentage',
                'status' => 'active',
                'image' => $file,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['ad' => ['id', 'title', 'image_url']]);

        Storage::disk('public')->assertExists('ads/' . $file->hashName());

        $this->assertDatabaseHas('ads', [
            'title' => 'Admin Ad',
        ]);
    }

    /** @test */
    public function admin_can_update_ad_and_replace_image()
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $oldFile = UploadedFile::fake()->image('old.jpg');
        $ad = Ad::factory()->create([
            'image_url' => $oldFile->store('ads', 'public')
        ]);

        $newFile = UploadedFile::fake()->image('new.jpg');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/ads/{$ad->id}", [
                'title' => 'Updated Ad',
                'image' => $newFile,
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Updated Ad']);

        // Old image deleted
        Storage::disk('public')->assertMissing($ad->image_url);

        // New image exists
        Storage::disk('public')->assertExists('ads/' . $newFile->hashName());

        $this->assertDatabaseHas('ads', [
            'id' => $ad->id,
            'title' => 'Updated Ad',
        ]);
    }

    /** @test */
    public function admin_can_delete_ad_and_image()
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('ad.jpg');
        $ad = Ad::factory()->create([
            'image_url' => $file->store('ads', 'public')
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/ads/{$ad->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('ads', [
            'id' => $ad->id
        ]);

        Storage::disk('public')->assertMissing($ad->image_url);
    }

    /** @test */
    public function admin_can_view_single_ad()
    {
        $admin = User::factory()->admin()->create();
        $ad = Ad::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/ads/{$ad->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $ad->id]);
    }
}
