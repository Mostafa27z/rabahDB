<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageRealEstate extends Model
{
    protected $table = 'image_real_estate';

    protected $fillable = [
        'real_estate_id',
        'cover_image_original',
        'cover_image_watermark',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];
}
