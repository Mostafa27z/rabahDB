<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Ad extends Model
{
    use HasFactory;

    protected $table = 'ads';

    protected $fillable = [
        'title',
        'property_id',
        'distinction',
        'description',
        'content',
        'discount',
        'discount_type',
        'image_url',
        'cta_text',
        'cta_link',
        'button_title',
        'button_link',
        'status',
        'starts_at',
        'ends_at',
        'priority',
        'max_views',
    ];

    protected $casts = [
        'property_id' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'max_views' => 'integer',
    ];

    protected $appends = [
        'image_full_url',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Fallback for description from content
     */
    public function getDescriptionAttribute($value)
    {
        return $value ?? $this->attributes['content'] ?? null;
    }

    /**
     * Fallback for button_title from cta_text
     */
    public function getButtonTitleAttribute($value)
    {
        return $value ?? $this->attributes['cta_text'] ?? null;
    }

    /**
     * Fallback for button_link from cta_link
     */
    public function getButtonLinkAttribute($value)
    {
        return $value ?? $this->attributes['cta_link'] ?? null;
    }

    /**
     * Full URL for the ad image
     */
    public function getImageFullUrlAttribute()
    {
        $raw = $this->image_url;
        if (!$raw) {
            return null;
        }

        if (Str::startsWith($raw, ['http://', 'https://', 'data:', 'blob:'])) {
            return $raw;
        }

        $clean = ltrim(str_replace('storage/', '', $raw), '/');
        return asset('storage/' . $clean);
    }
}
