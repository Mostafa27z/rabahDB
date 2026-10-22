<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'view_mode',
        'filters',
        'columns',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
