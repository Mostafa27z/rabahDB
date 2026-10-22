<?php

namespace Database\Factories;

use App\Models\Ad;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdFactory extends Factory
{
    protected $model = Ad::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraph,
            'discount' => $this->faker->numberBetween(5, 50),
            'discount_type' => 'percentage',
            'image_url' => $this->faker->imageUrl(),
            'cta_text' => 'Buy Now',
            'cta_link' => 'https://example.com',
            'status' => 'active',
            'priority' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
        ];
    }

    public function inactive()
    {
        return $this->state(fn () => [
            'status' => 'paused'
        ]);
    }
}
