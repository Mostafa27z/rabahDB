<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'location',
        'property_type',
        'message',
        'notes',
        'available_time',
        'source',
        'status',
        'priority',
        'assigned_to',
    ];

    protected $with = ['assignedAgent'];

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
