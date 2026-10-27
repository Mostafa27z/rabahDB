<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserRating;

class RatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch Users (Assuming DatabaseSeeder has run)
        $admin = User::where('email', 'admin@example.com')->first();
        $lead1 = User::where('email', 'lead1@example.com')->first();
        $agent1 = User::where('email', 'agent1@example.com')->first();
        $agent2 = User::where('email', 'agent2@example.com')->first();
        $client1 = User::where('email', 'client1@example.com')->first();
        $client2 = User::where('email', 'client2@example.com')->first();

        // Ensure users exist before seeding
        if (!$admin || !$lead1 || !$agent1 || !$client1) {
            $this->command->error('Users not found! Please run DatabaseSeeder first.');
            return;
        }

        // Clear existing ratings to avoid duplicates during testing
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        UserRating::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $ratings = [
            // --- SCENARIO A: Public Ratings (Visible to everyone) ---
            [
                'user_id' => $agent1->id, // Rated User: Agent 1
                'rater_id' => $client1->id, // Rater: Client 1
                'rating' => 5,
                'comment' => 'Agent 1 was fantastic! Highly recommended.',
                'status' => 'public',
            ],
            [
                'user_id' => $agent1->id, // Rated User: Agent 1
                'rater_id' => $lead1->id, // Rater: Team Lead 1
                'rating' => 4,
                'comment' => 'Great performance this month.',
                'status' => 'public',
            ],

            // --- SCENARIO B: Private Ratings (Visible to Admin & Rated User only) ---
            // Simulating new ratings that haven't been approved/made public yet
            [
                'user_id' => $agent2->id, // Rated User: Agent 2
                'rater_id' => $client2->id, // Rater: Client 2
                'rating' => 3,
                'comment' => 'Service was okay, but response time was slow. (Private Feedback)',
                'status' => 'private',
            ],
            [
                'user_id' => $lead1->id, // Rated User: Team Lead 1
                'rater_id' => $agent1->id, // Rater: Agent 1
                'rating' => 5,
                'comment' => 'Best team lead ever! (Private feedback to lead)',
                'status' => 'private',
            ],

            // --- SCENARIO C: Hidden Ratings (Visible to Admin only) ---
            // Simulating inappropriate content hidden by admin
            [
                'user_id' => $agent1->id, // Rated User: Agent 1
                'rater_id' => $client2->id, // Rater: Client 2
                'rating' => 1,
                'comment' => 'This is a spam/inappropriate comment hidden by admin.',
                'status' => 'hidden',
            ],

            // --- SCENARIO D: Mixed Bag for Admin User ---
            [
                'user_id' => $admin->id, // Rated User: Admin
                'rater_id' => $lead1->id,
                'rating' => 5,
                'comment' => 'Great system admin!',
                'status' => 'public',
            ],
        ];

        foreach ($ratings as $data) {
            UserRating::create($data);
        }

        $this->command->info('Ratings seeded successfully!');
        $this->command->info('------------------------------------------------');
        $this->command->info("Agent 1 (ID: $agent1->id) has: 2 Public, 1 Hidden");
        $this->command->info("Agent 2 (ID: $agent2->id) has: 1 Private");
        $this->command->info("Lead 1  (ID: $lead1->id) has: 1 Private");
        $this->command->info('------------------------------------------------');
    }
}
