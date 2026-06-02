<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SnippetMeta extends Model
{
    use SoftDeletes;
    protected $table = 'snippetmeta';

    public $timestamps = true;

    protected $fillable = [
        'snippet_id',
        'meta_key',
        'meta_value',
        'value_type',
    ];

    public function snippet()
    {
        return $this->belongsTo(Snippet::class, 'snippet_id');
    }
}
