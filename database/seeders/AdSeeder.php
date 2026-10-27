<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ad;
use App\Models\Property;

use Illuminate\Support\Facades\Schema;

class AdSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Ad::truncate();
        Schema::enableForeignKeyConstraints();

        $properties = Property::take(4)->get();

        $ads = [
            [
                'title' => 'فلل راقية بتشطيبات عالمية في قلب التجمع الخامس',
                'distinction' => 'مجمعات سكنية فاخرة',
                'description' => 'تصاميم معمارية عصرية بمساحات رحبة وتسهيلات سداد ميسرة تصل إلى 8 سنوات بأرقى كمبوندات القاهرة الجديدة.',
                'content' => 'تصاميم معمارية عصرية بمساحات رحبة وتسهيلات سداد ميسرة تصل إلى 8 سنوات بأرقى كمبوندات القاهرة الجديدة.',
                'button_title' => 'استكشف الفلل المتاحة',
                'button_link' => isset($properties[0]) ? "/property/{$properties[0]->id}" : '/projects',
                'property_id' => $properties[0]->id ?? null,
                'status' => 'active',
                'priority' => 10,
                'image_url' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1400&auto=format&fit=crop&q=80',
                'starts_at' => now(),
                'ends_at' => now()->addMonths(12),
            ],
            [
                'title' => 'فرص استثمارية واعدة في أسيوط وسوهاج الجديدة',
                'distinction' => 'عوائد استثمارية مجزية',
                'description' => 'مكاتب ومحلات تجارية ووحدات سكنية بعوائد تأجيرية ممتازة ومواقع حيوية تضمن نمو رأس مالك.',
                'content' => 'مكاتب ومحلات تجارية ووحدات سكنية بعوائد تأجيرية ممتازة ومواقع حيوية تضمن نمو رأس مالك.',
                'button_title' => 'استكشف الفرص الاستثمارية',
                'button_link' => isset($properties[3]) ? "/property/{$properties[3]->id}" : '/projects',
                'property_id' => $properties[3]->id ?? null,
                'status' => 'active',
                'priority' => 8,
                'image_url' => 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1400&auto=format&fit=crop&q=80',
                'starts_at' => now(),
                'ends_at' => now()->addMonths(12),
            ],
            [
                'title' => 'شاليهات وفلل شاطئية برأس الحكمة - الساحل الشمالي',
                'distinction' => 'إطلالة بحرية ساحرة',
                'description' => 'استمتع بأجمل شواطئ البحر الأبيض المتوسط مع خدمات فندقية متكاملة وبحيرات كريستالية طوال العام.',
                'content' => 'استمتع بأجمل شواطئ البحر الأبيض المتوسط مع خدمات فندقية متكاملة وبحيرات كريستالية طوال العام.',
                'button_title' => 'استكشف وحدات الساحل',
                'button_link' => isset($properties[6]) ? "/property/{$properties[6]->id}" : '/projects',
                'property_id' => $properties[6]->id ?? null,
                'status' => 'active',
                'priority' => 6,
                'image_url' => 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=1400&auto=format&fit=crop&q=80',
                'starts_at' => now(),
                'ends_at' => now()->addMonths(12),
            ],
        ];

        foreach ($ads as $adData) {
            $adData['content'] = $adData['content'] ?? $adData['description'] ?? $adData['title'];
            Ad::create($adData);
        }
    }
}
