<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PropertySubmission extends Model
{
    use HasFactory;

    protected $table = 'property_submissions';

    protected $fillable = [
        'reference',
        'user_id',
        'client_name',
        'contact_phone',
        'email',
        'title',
        'property_type',
        'area_name',
        'area_sqm',
        'price',
        'price_per_sqm',
        'property_details',
        'images',
        'pdf_file',
        'status',
        'priority',
        'notes',
        'assigned_to',
        'converted_property_id',
    ];

    protected $casts = [
        'area_sqm'      => 'float',
        'price'         => 'float',
        'price_per_sqm' => 'float',
        'images'        => 'array',
        'pdf_file'      => 'array',
    ];

    protected $appends = [
        'cover_url',
        'image_urls',
        'pdf_file_url',
    ];

    protected $with = [
        'user:id,name,email,phone,image',
        'assignedAgent:id,name,email,image',
        'convertedProperty:id,reference,title,status',
    ];

    protected static function booted()
    {
        static::created(function (self $submission) {
            $ym = $submission->created_at ? $submission->created_at->format('ym') : now()->format('ym');
            $expectedRef = 'SUB-' . $ym . '-' . $submission->id;
            if ($submission->reference !== $expectedRef) {
                $submission->reference = $expectedRef;
                $submission->saveQuietly();
            }
        });
    }

    public function getReferenceAttribute($value): string
    {
        if (!empty($value)) {
            return $value;
        }
        $ym = $this->created_at ? $this->created_at->format('ym') : now()->format('ym');
        return 'SUB-' . $ym . '-' . $this->id;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedProperty()
    {
        return $this->belongsTo(Property::class, 'converted_property_id');
    }

    public function getImageUrlsAttribute(): array
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
                } else {
                    $urls[] = asset('storage/' . ltrim($item, '/'));
                }
            } elseif (is_array($item) && !empty($item['path'])) {
                $urls[] = asset('storage/' . ltrim($item['path'], '/'));
            }
        }

        return $urls;
    }

    public function getCoverUrlAttribute(): ?string
    {
        $urls = $this->image_urls;
        return $urls[0] ?? null;
    }

    public function getPdfFileUrlAttribute(): ?array
    {
        if (empty($this->pdf_file) || !is_array($this->pdf_file)) {
            return null;
        }

        $path = $this->pdf_file['path'] ?? null;
        if (!$path) {
            return null;
        }

        $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
            ? $path
            : asset('storage/' . ltrim($path, '/'));

        return [
            'name' => $this->pdf_file['name'] ?? basename($path),
            'url'  => $url,
            'path' => $path,
            'size' => $this->pdf_file['size'] ?? null,
        ];
    }
}

