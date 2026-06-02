<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchPublication extends Model
{
    protected $table = 'research_publications';

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'research_id',
        'publication_id',
        'publication_title', 
        'publication_link',
        'created_at',
    ];

    public function research()
    {
        return $this->belongsTo(Research::class, 'research_id');
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }
}
