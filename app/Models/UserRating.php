<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rater_id',
        'rating',
        'comment',
        'status',
    ];

    /**
     * Default attributes
     */
    protected $attributes = [
        'status' => 'private',
    ];

    /**
     * The user being rated
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who gave the rating
     */
    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_id');
    }
}
