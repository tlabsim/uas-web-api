<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMeta extends Model
{
    use SoftDeletes;
    
    protected $table = 'postmeta';

    public $timestamps = true;

    protected $fillable = [
        'post_id',
        'meta_key',
        'meta_value',
        'value_type',
        'is_searchable',
    ];

    protected $casts = [
        'post_id' => 'integer',
        'meta_value' => 'string',
        'value_type' => 'string',
        'is_searchable' => 'boolean',
    ];

    /**
     * Relationships
     */
    
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * Query Scopes
     */
    
    public function scopeSearchable($query)
    {
        return $query->where('is_searchable', true);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('meta_key', $key);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('value_type', $type);
    }

    /**
     * Get the typed value based on value_type
     */
    public function getTypedValueAttribute()
    {
        return match($this->value_type) {
            'int' => (int) $this->meta_value,
            'float' => (float) $this->meta_value,
            'bool' => filter_var($this->meta_value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->meta_value, true),
            'date' => $this->meta_value ? \Carbon\Carbon::parse($this->meta_value) : null,
            'datetime' => $this->meta_value ? \Carbon\Carbon::parse($this->meta_value) : null,
            default => $this->meta_value,
        };
    }

    /**
     * Set value and automatically detect type
     */
    public function setTypedValue($value): void
    {
        if (is_bool($value)) {
            $this->value_type = 'bool';
            $this->meta_value = $value ? '1' : '0';
        } elseif (is_int($value)) {
            $this->value_type = 'int';
            $this->meta_value = (string) $value;
        } elseif (is_float($value)) {
            $this->value_type = 'float';
            $this->meta_value = (string) $value;
        } elseif (is_array($value)) {
            $this->value_type = 'json';
            $this->meta_value = json_encode($value);
        } elseif ($value instanceof \Carbon\Carbon) {
            $this->value_type = 'datetime';
            $this->meta_value = $value->format('Y-m-d H:i:s');
        } else {
            $this->value_type = 'string';
            $this->meta_value = (string) $value;
        }
    }
}
