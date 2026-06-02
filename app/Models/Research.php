<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Research extends Model
{
    protected $table = 'researches';

    public $timestamps = true;

    protected $fillable = [
        'title',
        'excerpt',
        'description',
        'featured_image_uri',
        'keywords',
        'status',
    ];

    public function people()
    {
        return $this->hasMany(ResearchPeople::class, 'research_id');
    }

    public function publications()
    {
        return $this->hasMany(ResearchPublication::class, 'research_id');
    }
}
