<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealEstateTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user (admin)
        $this->user = User::factory()->create([
            'role' => 'admin'
        ]);
    }

    /** @test */
    public function user_can_fetch_properties_from_local_database()
    {
        Property::create([
            'title' => 'Test Local Property',
            'price' => 500000,
            'currency' => 'EGP',
            'status' => 'available',
            'user_id' => $this->user->id,
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/properties');

        $response->assertStatus(200);
        $this->assertEquals('Test Local Property', $response->json('data.0.title'));
    }

    /** @test */
    public function user_can_create_property()
    {
        $payload = [
            'title' => 'New Local Property',
            'price' => 250000,
            'currency' => 'EGP',
            'status' => 'new',
            'property_type' => 'شقة',
            'area_sqft' => 120,
            'area_name' => 'سوهاج',
            'property_details' => 'تفاصيل العقار الجديد',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/properties', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('properties', [
            'title' => 'New Local Property',
        ]);
    }

    /** @test */
    public function client_can_submit_property()
    {
        $payload = [
            'title' => 'عقار معروض من العميل',
            'propertyType' => 'فيلا',
            'price' => 1500000,
            'area' => 300,
            'areaName' => 'سوهاج الجديدة',
            'clientName' => 'أحمد محمد',
            'contactPhone' => '01012345678',
            'email' => 'client@example.com',
            'propertyDetails' => 'فيلا مميزة للبيع تشطيب سوبر لوكس',
        ];

        $response = $this->postJson('/api/properties/submit', $payload);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('properties', [
            'title' => 'عقار معروض من العميل',
            'source_name' => 'أحمد محمد',
        ]);
    }

    /** @test */
    public function search_finds_property_by_reference_or_title()
    {
        $property = Property::create([
            'title' => 'شقة فاخرة على الكورنيش',
            'reference' => 'RBH2610-99',
            'price' => 750000,
            'status' => 'available',
            'user_id' => $this->user->id,
            'created_by_id' => $this->user->id,
            'search_title' => ' شقة فاخرة على الكورنيش RBH2610-99 ',
            'search_text' => ' شقة فاخرة على الكورنيش RBH2610-99 ',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/properties?search=الكورنيش');

        $response->assertStatus(200);
        $this->assertEquals('شقة فاخرة على الكورنيش', $response->json('data.0.title'));
    }
}
