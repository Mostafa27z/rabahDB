<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Http::fake([
            '*/user.get.json' => \Illuminate\Support\Facades\Http::response([
                'result' => [
                    ['ID' => 123, 'EMAIL' => 'lead@example.com']
                ]
            ], 200),
            '*' => \Illuminate\Support\Facades\Http::response([
                'result' => [
                    ['ID' => 123]
                ]
            ], 200),
        ]);
    }

    /** @test */
    public function admin_can_crud_any_user()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        // Create a team lead with social links
        $response = $this->postJson('/api/users', [
            'name' => 'Team Lead A',
            'email' => 'lead@example.com',
            'password' => 'password123',
            'role' => 'team_lead',
            'instagram_link' => 'https://instagram.com/leadA',
            'tiktok_link' => 'https://tiktok.com/@leadA',
            'whatsapp_link' => 'https://wa.me/1234567890'
        ]);
        $response->assertStatus(201)
                 ->assertJson([
                     'role' => 'team_lead',
                     'instagram_link' => 'https://instagram.com/leadA',
                     'tiktok_link' => 'https://tiktok.com/@leadA',
                     'whatsapp_link' => 'https://wa.me/1234567890'
                 ]);

        $leadId = $response->json('id');

        // View the created user
        $this->getJson("/api/users/{$leadId}")
             ->assertStatus(200)
             ->assertJson([
                 'name' => 'Team Lead A',
                 'instagram_link' => 'https://instagram.com/leadA'
             ]);

        // Update user social links
        $this->putJson("/api/users/{$leadId}", [
            'name' => 'Team Lead Updated',
            'instagram_link' => 'https://instagram.com/updatedLead',
        ])->assertStatus(200)
          ->assertJson([
              'name' => 'Team Lead Updated',
              'instagram_link' => 'https://instagram.com/updatedLead'
          ]);

        // Delete user
        $this->deleteJson("/api/users/{$leadId}")
             ->assertStatus(200)
             ->assertJson(['message' => 'Deleted successfully']);
    }

    /** @test */
    public function team_lead_can_only_crud_agents_under_him()
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $this->actingAs($lead, 'sanctum');

        // Create an agent with social links
        $response = $this->postJson('/api/users', [
            'name' => 'Agent A',
            'email' => 'agent@example.com',
            'password' => 'password123',
            'role' => 'agent',
            'instagram_link' => 'https://instagram.com/agentA'
        ]);
        $response->assertStatus(201)
                 ->assertJson(['role' => 'agent', 'instagram_link' => 'https://instagram.com/agentA']);

        $agentId = $response->json('id');

        // Cannot create another team lead
        $this->postJson('/api/users', [
            'name' => 'Team Lead B',
            'email' => 'lead2@example.com',
            'password' => 'password123',
            'role' => 'team_lead'
        ])->assertStatus(403);

        // View agent
        $this->getJson("/api/users/{$agentId}")
             ->assertStatus(200)
             ->assertJson(['name' => 'Agent A', 'instagram_link' => 'https://instagram.com/agentA']);

        // Update agent
        $this->putJson("/api/users/{$agentId}", [
            'name' => 'Agent Updated',
            'tiktok_link' => 'https://tiktok.com/@agentUpdated'
        ])->assertStatus(200)
          ->assertJson([
              'name' => 'Agent Updated',
              'tiktok_link' => 'https://tiktok.com/@agentUpdated'
          ]);

        // Delete agent
        $this->deleteJson("/api/users/{$agentId}")
             ->assertStatus(200)
             ->assertJson(['message' => 'Deleted successfully']);
    }

    /** @test */
    public function team_lead_cannot_access_other_agents_or_roles()
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $otherAgent = User::factory()->create(['role' => 'agent']);
        $otherLead = User::factory()->create(['role' => 'team_lead']);
        $this->actingAs($lead, 'sanctum');

        // Try to view unrelated agent
        $this->getJson("/api/users/{$otherAgent->id}")
             ->assertStatus(403);

        // Try to view another lead
        $this->getJson("/api/users/{$otherLead->id}")
             ->assertStatus(403);
    }

    /** @test */
    public function agent_cannot_access_user_management()
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $this->actingAs($agent, 'sanctum');

        // Index
        $this->getJson('/api/users')->assertStatus(403);

        // Store
        $this->postJson('/api/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
            'role' => 'client'
        ])->assertStatus(403);

        // Show
        $this->getJson("/api/users/{$agent->id}")->assertStatus(403);

        // Update
        $this->putJson("/api/users/{$agent->id}", ['name' => 'Y'])
             ->assertStatus(403);

        // Delete
        $this->deleteJson("/api/users/{$agent->id}")->assertStatus(403);
    }

    /** @test */
    public function search_and_pagination_works()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        // Create 15 users with social links
        User::factory()->count(15)->create([
            'instagram_link' => 'https://instagram.com/testUser',
            'tiktok_link' => 'https://tiktok.com/@testUser'
        ]);

        // Get first page
        $response = $this->getJson('/api/users?page=1&per_page=10');
        $response->assertStatus(200)
                 ->assertJson(['page' => 1, 'per_page' => 10])
                 ->assertJsonCount(10, 'data')
                 ->assertJson(['total' => 16]); // 15 + admin

        // Search by email
        $user = User::first();
        $response = $this->getJson('/api/users?search='.$user->email);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['email' => $user->email]);
    }
}
