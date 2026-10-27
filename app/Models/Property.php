<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'title',
        'description',
        'property_details',
        'property_type',
        'purpose',
        'price',
        'previous_price',
        'currency',
        'price_changed_at',
        'area_sqft',
        'price_per_foot',
        'area_name',
        'city',
        'address',
        'google_map_url',
        'latitude',
        'longitude',
        'status',
        'status_changed_at',
        'priority',
        'is_back_to_market',
        'is_featured',
        'is_new',
        'cover_image',
        'cover_image_watermark',
        'images',
        'brochure_pdf',
        'company_details',
        'company_images',
        'source_name',
        'source_files',
        'source_notes',
        'user_id',
        'created_by_id',
        'updated_by_id',
        'is_promoted',
        'promoted_at',
        'promoted_by',
        'promoted_designer_id',
        'designer_ids',
        'campaign_links',
        'designer_campaigns',
        'designer_notes',
        'promotion_completed_at',
        'seo_keywords',
        'search_title',
        'search_text',
        'search_bedrooms',
    ];

    protected $casts = [
        'price' => 'float',
        'previous_price' => 'float',
        'area_sqft' => 'float',
        'price_per_foot' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_back_to_market' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_promoted' => 'boolean',
        'campaign_links' => 'array',
        'designer_campaigns' => 'array',
        'designer_ids' => 'array',
        'images' => 'array',
        'company_images' => 'array',
        'source_files' => 'array',
        'price_changed_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'promoted_at' => 'datetime',
        'promotion_completed_at' => 'datetime',
    ];

    protected $appends = [
        'cover_url',
        'gallery_urls',
        'company_image_urls',
        'source_file_urls',
        'brochure_url',
        'has_price_drop',
        'is_advertised',
        'active_ad',
        'is_bookmarked',
        'company_completion_rate',
        'formatted_details',
        'promoted_designers_details',
        'seo_keywords_list',
        'bedrooms',
        'bathrooms',
    ];

    /* =======================
       Boot & Reference Generator
    ======================= */

    protected static function booted()
    {
        static::created(function ($property) {
            $ym = $property->created_at ? $property->created_at->format('ym') : now()->format('ym');
            $expectedRef = 'Shj' . $ym . '-' . $property->id;
            $updates = [];
            if ($property->reference !== $expectedRef) {
                $property->reference = $expectedRef;
                $updates['reference'] = $expectedRef;
            }
            $index = \App\Services\PropertySearchEngineService::buildIndex($property);
            if (!empty($index)) {
                $property->forceFill($index);
                $updates = array_merge($updates, $index);
            }
            if (!empty($updates)) {
                $property->saveQuietly();
            }
        });

        static::saving(function (self $p) {
            if (! $p->exists || $p->isDirty(['reference', 'title', 'property_type', 'area_name', 'city',
                    'address', 'seo_keywords', 'description', 'property_details'])) {
                $p->forceFill(\App\Services\PropertySearchEngineService::buildIndex($p));
            }
        });
    }

    /**
     * Dynamic reference format: Shj{ym}-{id} (e.g. Shj2609-1)
     */
    public function getReferenceAttribute($value): string
    {
        if (!empty($value) && str_starts_with($value, 'Shj')) {
            return $value;
        }
        $ym = $this->created_at ? $this->created_at->format('ym') : now()->format('ym');
        return 'Shj' . $ym . '-' . $this->id;
    }

    /* =======================
       Relationships
    ======================= */

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function promoter()
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    public function promotedDesigner()
    {
        return $this->belongsTo(User::class, 'promoted_designer_id');
    }

    public function activities()
    {
        return $this->hasMany(PropertyActivity::class, 'property_id')->latest();
    }

    public function ads()
    {
        return $this->hasMany(Ad::class, 'property_id');
    }

    public function activeAd()
    {
        return $this->hasOne(Ad::class, 'property_id')->where('status', 'active')->latest('id');
    }

    public function getIsAdvertisedAttribute(): bool
    {
        if ($this->relationLoaded('activeAd')) {
            return !is_null($this->getRelation('activeAd'));
        }
        return $this->activeAd()->exists();
    }

    public function getActiveAdAttribute()
    {
        if ($this->relationLoaded('activeAd')) {
            return $this->getRelation('activeAd');
        }
        return $this->activeAd()->first();
    }

    public function bookmarks()
    {
        return $this->hasMany(PropertyBookmark::class, 'property_id');
    }

    public function getIsBookmarkedAttribute(): bool
    {
        $userId = auth('sanctum')->id();
        if (!$userId) {
            return false;
        }

        if ($this->relationLoaded('bookmarks')) {
            $bookmarks = $this->getRelation('bookmarks');
            return $bookmarks ? $bookmarks->where('user_id', $userId)->isNotEmpty() : false;
        }

        return $this->bookmarks()->where('user_id', $userId)->exists();
    }

    /* =======================
       Accessors & Helpers
    ======================= */

    /** Returns clean array of SEO keywords */
    public function getSeoKeywordsListAttribute(): array
    {
        if (empty($this->seo_keywords)) {
            return [];
        }
        if (is_array($this->seo_keywords)) {
            return $this->seo_keywords;
        }
        $parts = explode(',', (string) $this->seo_keywords);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    /** Returns list of designers assigned to this property */
    public function getPromotedDesignersDetailsAttribute(): array
    {
        $ids = $this->designer_ids;
        if (empty($ids) && $this->promoted_designer_id) {
            $ids = [$this->promoted_designer_id];
        }
        if (empty($ids)) {
            return [];
        }
        return User::whereIn('id', (array) $ids)
            ->select('id', 'name', 'email', 'image')
            ->get()
            ->toArray();
    }

    /** Returns cover URL (local storage or external URL) */
    public function getCoverUrlAttribute(): ?string
    {
        // 1. Watermarked local copy takes priority
        if ($this->cover_image_watermark && Storage::disk('public')->exists($this->cover_image_watermark)) {
            return asset('storage/' . $this->cover_image_watermark);
        }

        // 2. Original cover (supports local storage or external URL including Bitrix)
        if ($this->cover_image) {
            if (str_starts_with($this->cover_image, 'http://') || str_starts_with($this->cover_image, 'https://')) {
                return $this->cover_image;
            }
            if (Storage::disk('public')->exists($this->cover_image)) {
                return asset('storage/' . $this->cover_image);
            }
        }

        return null;
    }

    /** Returns gallery URLs (local storage or external URLs) */
    public function getGalleryUrlsAttribute(): array
    {
        if (!is_array($this->images)) {
            return [];
        }

        $urls = [];
        foreach ($this->images as $item) {
            if (is_string($item)) {
                if (str_starts_with($item, 'http://') || str_starts_with($item, 'https://')) {
                    $urls[] = $item;
                } elseif (Storage::disk('public')->exists($item)) {
                    $urls[] = asset('storage/' . $item);
                }
            } elseif (is_array($item)) {
                if (!empty($item['watermark']) && Storage::disk('public')->exists($item['watermark'])) {
                    $urls[] = asset('storage/' . $item['watermark']);
                } elseif (!empty($item['path'])) {
                    if (str_starts_with($item['path'], 'http://') || str_starts_with($item['path'], 'https://')) {
                        $urls[] = $item['path'];
                    } elseif (Storage::disk('public')->exists($item['path'])) {
                        $urls[] = asset('storage/' . $item['path']);
                    }
                } elseif (!empty($item['url'])) {
                    $urls[] = $item['url'];
                }
            }
        }

        return $urls;
    }

    /** Returns company internal database image URLs */
    public function getCompanyImageUrlsAttribute(): array
    {
        if (!is_array($this->company_images)) {
            return [];
        }

        $urls = [];
        foreach ($this->company_images as $item) {
            if (is_string($item)) {
                if (str_starts_with($item, 'http://') || str_starts_with($item, 'https://')) {
                    $urls[] = $item;
                } elseif (Storage::disk('public')->exists($item)) {
                    $urls[] = asset('storage/' . $item);
                }
            } elseif (is_array($item) && !empty($item['path'])) {
                if (str_starts_with($item['path'], 'http://') || str_starts_with($item['path'], 'https://')) {
                    $urls[] = $item['path'];
                } elseif (Storage::disk('public')->exists($item['path'])) {
                    $urls[] = asset('storage/' . $item['path']);
                }
            }
        }
        return $urls;
    }

    /** Returns source files URLs */
    public function getSourceFileUrlsAttribute(): array
    {
        if (!is_array($this->source_files)) {
            return [];
        }

        $files = [];
        foreach ($this->source_files as $item) {
            if (is_string($item)) {
                $url = (str_starts_with($item, 'http://') || str_starts_with($item, 'https://'))
                    ? $item
                    : (Storage::disk('public')->exists($item) ? asset('storage/' . $item) : null);
                if ($url) {
                    $files[] = [
                        'name' => basename($item),
                        'url' => $url,
                        'path' => $item,
                    ];
                }
            } elseif (is_array($item)) {
                $path = $item['path'] ?? null;
                $url = $item['url'] ?? ($path && Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null);
                if ($url) {
                    $files[] = [
                        'name' => $item['name'] ?? basename($path ?? 'file'),
                        'url' => $url,
                        'path' => $path,
                        'size' => $item['size'] ?? null,
                    ];
                }
            }
        }
        return $files;
    }

    public function getBrochureUrlAttribute(): ?string
    {
        if ($this->brochure_pdf) {
            if (str_starts_with($this->brochure_pdf, 'http')) {
                return $this->brochure_pdf;
            }
            return asset('storage/' . $this->brochure_pdf);
        }
        return null;
    }

    public function getHasPriceDropAttribute(): bool
    {
        return !empty($this->previous_price) && $this->price < $this->previous_price;
    }

    /**
     * Dynamic Price Per Foot (returns stored value or computes price / area_sqft)
     */
    public function getPricePerFootAttribute($value): ?float
    {
        if (!empty($value) && (float)$value > 0) {
            return (float)$value;
        }
        if (!empty($this->price) && !empty($this->area_sqft) && (float)$this->area_sqft > 0) {
            return round((float)$this->price / (float)$this->area_sqft, 2);
        }
        return null;
    }



    /**
     * Calculate internal company database data completion percentage (14 inputs).
     */
    public function getCompanyCompletionRateAttribute(): int
    {
        $fields = [
            // 1. Title / Name
            !empty(trim($this->title ?? '')),
            // 2. Priority
            !empty($this->priority) && $this->priority !== 'not selected' && $this->priority !== 'غير محدد' && $this->priority !== 'none',
            // 3. Property Type
            !empty(trim($this->property_type ?? '')),
            // 4. Purpose
            !empty(trim($this->purpose ?? '')),
            // 5. City / Emirate
            !empty(trim($this->city ?? '')),
            // 6. District / Area
            !empty(trim($this->area_name ?? '')),
            // 7. Price
            !is_null($this->price) && (float)$this->price > 0,
            // 9. Area (Sqft)
            !is_null($this->area_sqft) && (float)$this->area_sqft > 0,
            // 10. Company Details
            !empty(trim($this->company_details ?? '')) || !empty(trim($this->property_details ?? '')) || !empty(trim($this->description ?? '')),
            // 11. Company Database Images
            (!empty($this->company_images) && is_array($this->company_images) && count($this->company_images) > 0) || (!empty($this->images) && is_array($this->images) && count($this->images) > 0),
            // 12. Location / Google Map URL
            !empty(trim($this->google_map_url ?? '')) || (!is_null($this->latitude) && !is_null($this->longitude)),
            // 13. Source Name
            !empty(trim($this->source_name ?? '')),
            // 14. Source Files / Documents
            (!empty($this->source_files) && is_array($this->source_files) && count($this->source_files) > 0),
        ];

        $completed = count(array_filter($fields));
        $total = count($fields);

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get property details/description for website display and email alerts.
     * Returns only the authentic property details entered by user, without synthetic specs.
     */
    public function getFormattedDetailsAttribute(): string
    {
        // 1. Primary: Website marketing description entered by user
        if (!empty($this->description)) {
            $cleanDesc = trim(strip_tags($this->description));
            if (!empty($cleanDesc)) {
                return $cleanDesc;
            }
        }

        // 2. Secondary: Company internal details if provided
        if (!empty($this->company_details)) {
            $cleanComp = trim(strip_tags($this->company_details));
            if (!empty($cleanComp)) {
                return $cleanComp;
            }
        }

        // 3. Fallback: If property_details is plain text (not JSON)
        $raw = $this->property_details;
        if (!empty($raw) && is_string($raw) && !str_starts_with(trim($raw), '{') && !str_starts_with(trim($raw), '[')) {
            return trim(strip_tags($raw));
        }

        return '';
    }

    public function getBedroomsAttribute(): ?string
    {
        if (!empty($this->search_bedrooms)) {
            return (string)$this->search_bedrooms;
        }
        if (!empty($this->property_details)) {
            $details = is_array($this->property_details) ? $this->property_details : json_decode($this->property_details, true);
            if (!empty($details['bedrooms'])) {
                return (string)$details['bedrooms'];
            }
        }
        return null;
    }

    public function getBathroomsAttribute(): ?string
    {
        if (!empty($this->property_details)) {
            $details = is_array($this->property_details) ? $this->property_details : json_decode($this->property_details, true);
            if (!empty($details['bathrooms'])) {
                return (string)$details['bathrooms'];
            }
        }
        return null;
    }
}
