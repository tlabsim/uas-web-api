<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $table = 'publications';

    public $timestamps = true;

    protected $fillable = [
        'title',
        'excerpt',
        'description',
        'publication_date',
        'type',
        'link_url',
        'keywords',
    ];

    protected $casts = [
        'publication_date' => 'date',
        'type' => 'string',
        'keywords' => 'array', // Assuming keywords is stored as a JSON array
    ];

    public function authors()
    {
        return $this->hasMany(PublicationAuthor::class, 'publication_id');
    }

    public function meta()
    {
        return $this->hasMany(PublicationMeta::class, 'publication_id');
    }
}
