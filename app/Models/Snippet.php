<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Snippet extends Model
{
    use SoftDeletes;
    protected $table = 'snippets';

    public $timestamps = true;

    protected $fillable = [
        'snippet_group',
        'slug',
        'entity_id',
        'name',
        'content',
        'tags',
        'status',
        'published_at',
        'content_last_edited_at',
    ];

    public function entity()
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id');
    }

    public function meta()
    {
        return $this->hasMany(SnippetMeta::class, 'snippet_id');
    }
}
