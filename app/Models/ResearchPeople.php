<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchPeople extends Model
{
    protected $table = 'research_peoples';

    public $timestamps = true;

    protected $fillable = [
        'research_id',
        'researcher_name',
        'internal_researcher_id',
        'external_researcher_profile_link',
        'role',
        'sl',
        'is_primary_editor',
        'is_editor',
    ];

    protected $casts = [
        'is_primary_editor' => 'boolean',
        'is_editor' => 'boolean',
    ];

    public function research()
    {
        return $this->belongsTo(Research::class, 'research_id');
    }
}
