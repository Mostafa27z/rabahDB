<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1️⃣ Create Admin
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => bcrypt('password123'),
        ]);

        // 2️⃣ Create Team Leads
        $teamLead1 = User::factory()->create([
            'name' => 'Team Lead 1',
            'email' => 'lead1@example.com',
            'role' => 'team_lead',
            'password' => bcrypt('password123'),
        ]);

        $teamLead2 = User::factory()->create([
            'name' => 'Team Lead 2',
            'email' => 'lead2@example.com',
            'role' => 'team_lead',
            'password' => bcrypt('password123'),
        ]);

        // 3️⃣ Create Agents linked to team leads
        $agents = [
            ['name' => 'Agent 1', 'email' => 'agent1@example.com', 'team_lead_id' => $teamLead1->id],
            ['name' => 'Agent 2', 'email' => 'agent2@example.com', 'team_lead_id' => $teamLead1->id],
            ['name' => 'Agent 3', 'email' => 'agent3@example.com', 'team_lead_id' => $teamLead2->id],
            ['name' => 'Agent 4', 'email' => 'agent4@example.com', 'team_lead_id' => $teamLead2->id],
        ];

        foreach ($agents as $agentData) {
            User::factory()->create(array_merge($agentData, [
                'role' => 'agent',
                'password' => bcrypt('password123'),
            ]));
        }

        // 4️⃣ Create Independent Clients
        $clients = [
            ['name' => 'Client 1', 'email' => 'client1@example.com', 'team_lead_id' => $teamLead1->id],
            ['name' => 'Client 2', 'email' => 'client2@example.com', 'team_lead_id' => $teamLead2->id],
        ];

        foreach ($clients as $clientData) {
            User::factory()->create(array_merge($clientData, [
                'role' => 'client',
                'password' => bcrypt('password123'),
            ]));
        }

        // Optional: create random additional users
        User::factory(2)->create();
    }
}
