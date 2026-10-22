<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CompanyDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_company_details()
    {
        CompanyDetail::create([
            'phone' => '+971 6 566 2233',
            'mobile' => '+971 50 228 8787',
            'management_email' => 'info@tamleekuae.ae',
        ]);

        $response = $this->getJson('/api/company-details');

        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'phone' => '+971 6 566 2233',
                'mobile' => '+971 50 228 8787',
                'management_email' => 'info@tamleekuae.ae',
            ]);
    }

    /** @test */
    public function guest_cannot_store_company_details()
    {
        $response = $this->postJson('/api/company-details', [
            'phone' => '+971 6 566 2233',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_create_company_details()
    {
        $user = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($user);

        $payload = [
            'phone' => '+971 6 566 2233',
            'mobile' => '+971 50 228 8787',
            'management_email' => 'info@tamleekuae.ae',
            'clients_email' => 'info@tamleekuae.ae',
            'support_email' => 'support@tamleekuae.ae',
            'career_email' => 'career@tamleekuae.ae',
            'linkedin' => 'https://linkedin.com/company/tamleek',
            'facebook' => 'https://facebook.com/tamleek',
            'instagram' => 'https://instagram.com/tamleek',
            'tiktok' => 'https://tiktok.com/@tamleek',
            'whatsapp' => 'https://wa.me/971502288787',
            'first_shift' => '9:00 AM - 6:00 PM',
            'second_shift' => '10:00 AM - 7:00 PM',
            'holiday' => 'Friday',
        ];

        $response = $this->postJson('/api/company-details', $payload);

        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'phone' => '+971 6 566 2233',
                'career_email' => 'career@tamleekuae.ae',
                'first_shift' => '9:00 AM - 6:00 PM',
            ]);

        $this->assertDatabaseHas('company_details', [
            'phone' => '+971 6 566 2233',
            'support_email' => 'support@tamleekuae.ae',
            'second_shift' => '10:00 AM - 7:00 PM',
            'holiday' => 'Friday',
        ]);
    }

    /** @test */
    public function authenticated_user_can_update_company_details()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        CompanyDetail::create([
            'phone' => '+971 6 566 2233',
        ]);

        $response = $this->postJson('/api/company-details', [
            'phone' => '+971 6 999 9999',
            'mobile' => '+971 55 000 0000',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('company_details', [
            'phone' => '+971 6 999 9999',
            'mobile' => '+971 55 000 0000',
        ]);
    }

    /** @test */
    public function non_admin_cannot_update_company_details()
    {
        $user = User::factory()->create(['role' => 'agent']); // Use a non-admin role
        Sanctum::actingAs($user);

        CompanyDetail::create([
            'phone' => '+971 6 566 2233',
        ]);

        $response = $this->postJson('/api/company-details', [
            'phone' => '+971 6 999 9999',
        ]);

        $response->assertStatus(403);
    }
}
