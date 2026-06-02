<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostMeta;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostMetaService
{
    /**
     * Validate metadata against category schema
     *
     * @param PostCategory $category
     * @param array $metadata
     * @return array Validated metadata
     * @throws ValidationException
     */
    public function validateMetadata(PostCategory $category, array $metadata): array
    {
        $rules = [];
        $messages = [];
        $allFields = $category->all_fields;

        foreach ($allFields as $field) {
            $key = $field['key'];
            $fieldRules = [];

            // Required validation
            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type-based validation
            switch ($field['type']) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'number':
                case 'int':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'datetime':
                    $fieldRules[] = 'date_format:Y-m-d H:i:s';
                    break;
                case 'time':
                    $fieldRules[] = 'date_format:H:i';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'select':
                    if (isset($field['options']) && is_array($field['options'])) {
                        $fieldRules[] = 'in:' . implode(',', $field['options']);
                    }
                    break;
            }

            // Custom validation rules from schema
            if (isset($field['validation'])) {
                $fieldRules[] = $field['validation'];
            }

            $rules["meta.{$key}"] = implode('|', $fieldRules);
            
            // Custom error messages
            if (isset($field['label'])) {
                $messages["meta.{$key}.required"] = "The {$field['label']} field is required.";
            }
        }

        $validator = Validator::make(['meta' => $metadata], $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated()['meta'];
    }

    /**
     * Save metadata for a post
     *
     * @param Post $post
     * @param array $metadata
     * @param PostCategory|null $category
     * @return void
     */
    public function saveMetadata(Post $post, array $metadata, ?PostCategory $category = null): void
    {
        $category = $category ?? $post->postCategory;

        foreach ($metadata as $key => $value) {
            // Skip empty values
            if ($value === null || $value === '') {
                continue;
            }

            // Determine if this field is searchable from schema
            $isSearchable = true;
            $valueType = 'string';

            if ($category) {
                $fieldConfig = $category->getField($key);
                if ($fieldConfig) {
                    $isSearchable = $fieldConfig['searchable'] ?? true;
                    $valueType = $this->mapFieldTypeToValueType($fieldConfig['type'] ?? 'text');
                }
            }

            // Create or update metadata
            $post->setMeta($key, $value, $valueType, $isSearchable);
        }
    }

    /**
     * Delete metadata keys that are not in the provided array
     *
     * @param Post $post
     * @param array $keepKeys
     * @return void
     */
    public function pruneMetadata(Post $post, array $keepKeys): void
    {
        $post->metadata()
             ->whereNotIn('meta_key', $keepKeys)
             ->delete();
    }

    /**
     * Get metadata value by key
     *
     * @param Post $post
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMeta(Post $post, string $key, $default = null)
    {
        return $post->getMeta($key, $default);
    }

    /**
     * Search posts by metadata
     *
     * @param string $key
     * @param mixed $value
     * @param string $operator
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function searchByMeta(string $key, $value, string $operator = '=')
    {
        return Post::withMeta($key, $operator, $value);
    }

    /**
     * Get all metadata for a post as associative array
     *
     * @param Post $post
     * @return array
     */
    public function getAllMetadata(Post $post): array
    {
        return $post->getAllMeta();
    }

    /**
     * Bulk save metadata from request
     *
     * @param Post $post
     * @param array $requestData Should contain 'meta' key with metadata array
     * @return void
     * @throws ValidationException
     */
    public function saveFromRequest(Post $post, array $requestData): void
    {
        if (!isset($requestData['meta']) || !is_array($requestData['meta'])) {
            return;
        }

        $metadata = $requestData['meta'];
        $category = $post->postCategory;

        if ($category) {
            // Validate against schema
            $metadata = $this->validateMetadata($category, $metadata);
        }

        // Save all metadata
        $this->saveMetadata($post, $metadata, $category);
    }

    /**
     * Get metadata organized by required and extra fields
     *
     * @param Post $post
     * @return array ['required' => [], 'extra' => []]
     */
    public function getOrganizedMetadata(Post $post): array
    {
        $category = $post->postCategory;
        $allMeta = $post->getAllMeta();

        if (!$category) {
            return [
                'required' => [],
                'extra' => $allMeta,
            ];
        }

        $requiredKeys = collect($category->required_fields)->pluck('key')->toArray();
        $extraKeys = collect($category->extra_fields)->pluck('key')->toArray();

        $required = [];
        $extra = [];

        foreach ($allMeta as $key => $value) {
            if (in_array($key, $requiredKeys)) {
                $required[$key] = $value;
            } elseif (in_array($key, $extraKeys)) {
                $extra[$key] = $value;
            } else {
                // Custom field not in schema
                $extra[$key] = $value;
            }
        }

        return [
            'required' => $required,
            'extra' => $extra,
        ];
    }

    /**
     * Map field type from schema to database value_type
     *
     * @param string $fieldType
     * @return string
     */
    private function mapFieldTypeToValueType(string $fieldType): string
    {
        return match($fieldType) {
            'number', 'int' => 'int',
            'float', 'decimal' => 'float',
            'boolean', 'checkbox' => 'bool',
            'date' => 'date',
            'datetime', 'datetime-local' => 'datetime',
            'json', 'array' => 'json',
            default => 'string',
        };
    }

    /**
     * Get metadata with proper type casting
     *
     * @param Post $post
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getTypedMeta(Post $post, string $key, $default = null)
    {
        $meta = $post->metadata()->where('meta_key', $key)->first();
        
        if (!$meta) {
            return $default;
        }

        return $meta->typed_value;
    }
}
