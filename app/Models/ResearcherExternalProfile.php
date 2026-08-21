<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearcherExternalProfile extends Model
{
    protected $table = 'researcher_external_profiles';

    public $timestamps = true;

    protected $fillable = [
        'researcher_id',
        'profile_type',
        'profile_id',
        'profile_link',
    ];

    public function researcher()
    {
        return $this->belongsTo(Researcher::class, 'researcher_id');
    }
}
