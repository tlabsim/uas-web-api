<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PostAttachment extends Model
{
    use SoftDeletes;
    
    protected $table = 'post_attachments';

    public $timestamps = true;

    protected $fillable = [
        'post_id',
        'attachment_title',
        'attachment_uri',
        'attachment_type',
        'file_name',
        'file_size',
        'mime_type',
        'description',
        'sort_order',
        'uploaded_by',
    ];

    protected $casts = [
        'post_id' => 'integer',
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'uploaded_by' => 'integer',
    ];

    /**
     * Relationships
     */
    
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Query Scopes
     */
    
    public function scopeByType($query, string $type)
    {
        return $query->where('attachment_type', $type);
    }

    public function scopeDocuments($query)
    {
        return $query->where('attachment_type', 'document');
    }

    public function scopeImages($query)
    {
        return $query->where('attachment_type', 'image');
    }

    public function scopeVideos($query)
    {
        return $query->where('attachment_type', 'video');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Helper Methods
     */
    
    /**
     * Get full URL to the attachment
     */
    public function getUrlAttribute(): string
    {
        if (filter_var($this->attachment_uri, FILTER_VALIDATE_URL)) {
            return $this->attachment_uri;
        }
        
        return Storage::url($this->attachment_uri);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Check if attachment is an image
     */
    public function isImage(): bool
    {
        return $this->attachment_type === 'image' || 
               str_starts_with($this->mime_type ?? '', 'image/');
    }

    /**
     * Check if attachment is a document
     */
    public function isDocument(): bool
    {
        return $this->attachment_type === 'document' ||
               in_array($this->mime_type, [
                   'application/pdf',
                   'application/msword',
                   'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
               ]);
    }

    /**
     * Check if attachment is a video
     */
    public function isVideo(): bool
    {
        return $this->attachment_type === 'video' ||
               str_starts_with($this->mime_type ?? '', 'video/');
    }

    /**
     * Delete file from storage when model is deleted
     */
    protected static function booted()
    {
        static::deleting(function ($attachment) {
            if ($attachment->attachment_uri && !filter_var($attachment->attachment_uri, FILTER_VALIDATE_URL)) {
                Storage::delete($attachment->attachment_uri);
            }
        });
    }
}
