<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\PostCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostAttachmentService
{
    /**
     * Upload and attach files to a post
     *
     * @param Post $post
     * @param array $files Array of UploadedFile instances
     * @param array $metadata Optional metadata for each file (titles, descriptions)
     * @return array Array of created PostAttachment models
     */
    public function attachFiles(Post $post, array $files, array $metadata = []): array
    {
        $attachments = [];
        $sortOrder = $post->attachments()->max('sort_order') ?? 0;

        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $sortOrder++;
            
            $attachment = $this->uploadFile($post, $file, [
                'title' => $metadata[$index]['title'] ?? null,
                'description' => $metadata[$index]['description'] ?? null,
                'sort_order' => $sortOrder,
            ]);

            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * Upload a single file and create attachment record
     *
     * @param Post $post
     * @param UploadedFile $file
     * @param array $options
     * @return PostAttachment
     */
    public function uploadFile(Post $post, UploadedFile $file, array $options = []): PostAttachment
    {
        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $originalName = $file->getClientOriginalName();
        $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;

        // Store file
        $path = $file->storeAs("post-attachments/{$post->id}", $filename, 'public');

        // Determine attachment type
        $mimeType = $file->getMimeType();
        $attachmentType = $this->determineAttachmentType($mimeType);

        // Create attachment record
        return $post->attachments()->create([
            'attachment_title' => $options['title'] ?? $originalName,
            'attachment_uri' => $path,
            'attachment_type' => $attachmentType,
            'file_name' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $mimeType,
            'description' => $options['description'] ?? null,
            'sort_order' => $options['sort_order'] ?? 0,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Validate attachments against category requirements
     *
     * @param PostCategory $category
     * @param array $files
     * @throws ValidationException
     */
    public function validateAttachments(PostCategory $category, array $files): void
    {
        $config = $category->attachment_config ?? [];
        
        // Check if attachments are required
        if (($config['required'] ?? false) && empty($files)) {
            throw ValidationException::withMessages([
                'attachments' => ['At least one attachment is required for this category.']
            ]);
        }

        // Check maximum files
        $maxFiles = $config['max_files'] ?? 10;
        if (count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'attachments' => ["You can upload a maximum of {$maxFiles} files."]
            ]);
        }

        // Check allowed types
        if (isset($config['allowed_types']) && is_array($config['allowed_types'])) {
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $mimeType = $file->getMimeType();
                $fileType = $this->determineAttachmentType($mimeType);

                if (!in_array($fileType, $config['allowed_types'])) {
                    throw ValidationException::withMessages([
                        'attachments' => [
                            "Only " . implode(', ', $config['allowed_types']) . " files are allowed for this category."
                        ]
                    ]);
                }
            }
        }
    }

    /**
     * Delete an attachment
     *
     * @param PostAttachment $attachment
     * @return bool
     */
    public function deleteAttachment(PostAttachment $attachment): bool
    {
        return $attachment->delete();
    }

    /**
     * Delete multiple attachments
     *
     * @param array $attachmentIds
     * @return int Number of deleted attachments
     */
    public function deleteAttachments(array $attachmentIds): int
    {
        return PostAttachment::whereIn('id', $attachmentIds)->delete();
    }

    /**
     * Update attachment metadata
     *
     * @param PostAttachment $attachment
     * @param array $data
     * @return PostAttachment
     */
    public function updateAttachment(PostAttachment $attachment, array $data): PostAttachment
    {
        $attachment->update([
            'attachment_title' => $data['title'] ?? $attachment->attachment_title,
            'description' => $data['description'] ?? $attachment->description,
            'sort_order' => $data['sort_order'] ?? $attachment->sort_order,
        ]);

        return $attachment->fresh();
    }

    /**
     * Reorder attachments
     *
     * @param array $order Array of ['id' => sort_order]
     * @return void
     */
    public function reorderAttachments(array $order): void
    {
        foreach ($order as $id => $sortOrder) {
            PostAttachment::where('id', $id)->update(['sort_order' => $sortOrder]);
        }
    }

    /**
     * Get attachments by type
     *
     * @param Post $post
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAttachmentsByType(Post $post, string $type)
    {
        return $post->attachments()->byType($type)->ordered()->get();
    }

    /**
     * Determine attachment type from MIME type
     *
     * @param string $mimeType
     * @return string
     */
    private function determineAttachmentType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ])) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Get attachment configuration for a category
     *
     * @param PostCategory $category
     * @return array
     */
    public function getAttachmentConfig(PostCategory $category): array
    {
        return [
            'required' => $category->attachments_required,
            'max_files' => $category->max_files,
            'allowed_types' => $category->allowed_types,
            'help_text' => $category->attachment_config['help_text'] ?? null,
        ];
    }
}
