<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationMeta extends Model
{
    protected $table = 'publication_meta';

    public $timestamps = true;

    protected $fillable = [
        'publication_id',
        'meta_key',
        'meta_value',
    ];

    public function publication()
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }
}
