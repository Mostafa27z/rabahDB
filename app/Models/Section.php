<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_name',
        'p1',
        'p2',
        'p3',
    ];

    public function subsections()
    {
        return $this->hasMany(Subsection::class, 'section_id');
    }
}
