<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDetail extends Model
{
    protected $fillable = [
        'phone',
        'mobile',
        'management_email',
        'clients_email',
        'support_email',
        'career_email',
        'linkedin',
        'facebook',
        'twitter',
        'tiktok',
        'instagram',
        'whatsapp',
        'snapchat',
        'telegram',
        'first_shift',
        'second_shift',
        'holiday',
        'navbar_logo',
        'footer_logo',
        'watermark_image',
        'watermark_type',
        'watermark_position',
        'watermark_opacity',
        'watermark_size',
    ];

    protected $appends = [
        'navbar_logo_url',
        'footer_logo_url',
        'watermark_image_url',
    ];

    public function getNavbarLogoUrlAttribute()
    {
        return $this->navbar_logo ? asset('storage/' . $this->navbar_logo) : null;
    }

    public function getFooterLogoUrlAttribute()
    {
        return $this->footer_logo ? asset('storage/' . $this->footer_logo) : null;
    }

    public function getWatermarkImageUrlAttribute()
    {
        return $this->watermark_image ? asset('storage/' . $this->watermark_image) : null;
    }
}
