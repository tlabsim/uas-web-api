<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityCache extends Model
{
    protected $table = 'entities_cache';
    protected $primaryKey = 'entity_id';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'entity_id',
        'entity_type',
        'entity_category',
        'parent_entity_id',
        'parent_entity_name',
        'title',
        'title_bn',
        'name',        
        'name_bn',
        'display_name',
        'display_name_bn',
        'short_name',
        'short_name_bn',
        'description',
        'logo_url',
        'address',
        'address_synced_at',
        'entity_order',
    ];

    protected $casts = [       
        'entity_order' => 'integer',
        'address' => 'array',
        'address_synced_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
        'full_name_bn',
    ];

    public function profile()
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id');
    }

    //Full name attribute combining title and name
    public function getFullNameAttribute()
    {
        return $this->display_name ?: trim("{$this->title} {$this->name}");
    }

    //Full name in Bangla attribute combining title_bn and name_bn
    public function getFullNameBnAttribute()
    {
        return $this->display_name_bn ?: trim("{$this->title_bn} {$this->name_bn}");
    }
}
