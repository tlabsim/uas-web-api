<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationAuthor extends Model
{
    protected $table = 'publication_authors';

    public $timestamps = true;

    protected $fillable = [
        'publication_id',
        'author_name',
        'internal_author_id',
        'external_author_profile_link',
        'sl',
        'is_primary_editor',
        'is_editor',
        'show_in_profile',
    ];

    protected $casts = [
        'is_primary_editor' => 'boolean',
        'is_editor' => 'boolean',
        'show_in_profile' => 'boolean',
    ];

    public function publication()
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }
}
