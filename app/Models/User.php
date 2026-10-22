<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable ,HasApiTokens;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'team_lead_id',
        'phone',
        'instagram_link',
        'tiktok_link',
        'whatsapp_link',
        'facebook_link',
        'linkedin_link',
        'twitter_link',
        'snapchat_link',
        'telegram_link',
        'bio',
        'image',
        'portfolio_views',
        'username',
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
        
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'portfolio_views' => 'integer',
        ];
    }

    /**
     * Get image URL
     */
    public function getImageAttribute($value)
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        return asset('storage/' . $value);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->image);
            }
        });
    }

    /* =======================
       Relationships
    ======================= */

    // Client / Agent → belongs to Team Lead
    public function teamLead()
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    // Team Lead → has many Agents
    public function agents()
    {
        return $this->hasMany(User::class, 'team_lead_id')
            ->where('role', 'agent');
    }

    // Ratings received by this user
    public function ratingsReceived()
    {
        return $this->hasMany(UserRating::class, 'user_id');
    }

    // Ratings given by this user
    public function ratingsGiven()
    {
        return $this->hasMany(UserRating::class, 'rater_id');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'user_id');
    }

    /* =======================
       Role helpers
    ======================= */

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTeamLead()
    {
        return $this->role === 'team_lead';
    }

    public function isAgent()
    {
        return $this->role === 'agent';
    }

    public function isClient()
    {
        return $this->role === 'client';
    }

    public function isDesigner()
    {
        return $this->role === 'designer';
    }

    /**
     * Get average rating for this user
     */
    public function averageRating()
    {
        return $this->ratingsReceived()->avg('rating');
    }

    public function bookmarks()
    {
        return $this->hasMany(PropertyBookmark::class, 'user_id');
    }

    public function bookmarkedProperties()
    {
        return $this->belongsToMany(Property::class, 'property_bookmarks', 'user_id', 'property_id')->withTimestamps();
    }
}
