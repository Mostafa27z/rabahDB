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
            'username' => 'admin',
            'role' => 'admin',
            'phone' => '+201001234567',
            'bio' => 'المشرف العام على النظام وإدارة العقارات',
            'password' => bcrypt('password123'),
        ]);

        // 2️⃣ Create Team Leads
        $teamLead1 = User::factory()->create([
            'name' => 'أحمد محمود',
            'email' => 'lead1@example.com',
            'username' => 'lead1',
            'role' => 'team_lead',
            'phone' => '+201012345678',
            'bio' => 'قائد فريق المبيعات - القاهرة والجيزة',
            'password' => bcrypt('password123'),
        ]);

        $teamLead2 = User::factory()->create([
            'name' => 'محمد إبراهيم',
            'email' => 'lead2@example.com',
            'username' => 'lead2',
            'role' => 'team_lead',
            'phone' => '+201098765432',
            'bio' => 'قائد فريق المبيعات - سوهاج وأسيوط',
            'password' => bcrypt('password123'),
        ]);

        // 3️⃣ Create Agents
        $agents = [
            [
                'name' => 'سارة علي',
                'email' => 'agent1@example.com',
                'username' => 'agent1',
                'phone' => '+201112233445',
                'team_lead_id' => $teamLead1->id,
                'bio' => 'مستشارة عقارية متخصصة في عقارات التجمع الخامس والشيخ زايد بخبرة 7 سنوات.',
            ],
            [
                'name' => 'كريم حسن',
                'email' => 'agent2@example.com',
                'username' => 'agent2',
                'phone' => '+201122334455',
                'team_lead_id' => $teamLead1->id,
                'bio' => 'خبير استثمار تجاري وإداري في العاصمة الإدارية والتجمع.',
            ],
            [
                'name' => 'عمر خالد',
                'email' => 'agent3@example.com',
                'username' => 'agent3',
                'phone' => '+201133445566',
                'team_lead_id' => $teamLead2->id,
                'bio' => 'مستشار عقاري متخصص في سوهاج وأسيوط الجديدة والأراضي السكنية.',
            ],
            [
                'name' => 'نور الهدى',
                'email' => 'agent4@example.com',
                'username' => 'agent4',
                'phone' => '+201144556677',
                'team_lead_id' => $teamLead2->id,
                'bio' => 'متخصصة في الشقق السكنية والفندقية والإيجارات الفاخرة.',
            ],
        ];

        foreach ($agents as $agentData) {
            User::factory()->create(array_merge($agentData, [
                'role' => 'agent',
                'password' => bcrypt('password123'),
            ]));
        }

        // 4️⃣ Create Designers
        User::factory()->create([
            'name' => 'مصطفى كمال',
            'email' => 'designer1@example.com',
            'username' => 'designer1',
            'role' => 'designer',
            'phone' => '+201155667788',
            'password' => bcrypt('password123'),
        ]);

        // 5️⃣ Create Clients
        $clients = [
            ['name' => 'عميل 1 - طارق فهمي', 'email' => 'client1@example.com', 'username' => 'client1', 'team_lead_id' => $teamLead1->id],
            ['name' => 'عميل 2 - منى السعيد', 'email' => 'client2@example.com', 'username' => 'client2', 'team_lead_id' => $teamLead2->id],
        ];

        foreach ($clients as $clientData) {
            User::factory()->create(array_merge($clientData, [
                'role' => 'client',
                'password' => bcrypt('password123'),
            ]));
        }

        // 6️⃣ Run Sub-Seeders
        $this->call([
            CompanyDetailSeeder::class,
            PropertySeeder::class,
            AdSeeder::class,
            RatingSeeder::class,
        ]);
    }
}
