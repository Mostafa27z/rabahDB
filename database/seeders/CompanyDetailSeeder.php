<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanyDetail;

use Illuminate\Support\Facades\Schema;

class CompanyDetailSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        CompanyDetail::truncate();
        Schema::enableForeignKeyConstraints();

        CompanyDetail::create([
            'phone' => '+20 2 2345 6789',
            'mobile' => '+20 100 123 4567',
            'management_email' => 'management@rabah.eg',
            'clients_email' => 'info@rabah.eg',
            'support_email' => 'support@rabah.eg',
            'career_email' => 'careers@rabah.eg',
            'facebook' => 'https://facebook.com/rabah.realestate',
            'twitter' => 'https://twitter.com/rabah_eg',
            'tiktok' => 'https://tiktok.com/@rabah_realestate',
            'instagram' => 'https://instagram.com/rabah_realestate',
            'whatsapp' => '+201001234567',
            'first_shift' => '09:00 ص - 05:00 م',
            'second_shift' => '05:00 م - 10:00 م',
            'holiday' => 'الجمعة والسبت',
            'watermark_type' => 'custom',
            'watermark_position' => 'center',
            'watermark_opacity' => 30,
            'watermark_size' => 20,
        ]);
    }
}
