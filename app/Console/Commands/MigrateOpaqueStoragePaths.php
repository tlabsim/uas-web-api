<?php

namespace App\Console\Commands;

use App\Models\MediaItem;
use App\Models\PostAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateOpaqueStoragePaths extends Command
{
    protected $signature = 'storage:migrate-opaque-paths
        {--media : Migrate media library files only}
        {--attachments : Migrate post attachments only}
        {--dry-run : Show planned moves without changing files or database}
        {--force : Skip confirmation prompt}';

    protected $description = 'Move existing media library files and post attachments into opaque storage buckets.';

    public function handle(): int
    {
        $migrateMedia = (bool) $this->option('media');
        $migrateAttachments = (bool) $this->option('attachments');

        if (!$migrateMedia && !$migrateAttachments) {
            $migrateMedia = true;
            $migrateAttachments = true;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !$this->option('force')) {
            $targets = collect([
                $migrateMedia ? 'media library files' : null,
                $migrateAttachments ? 'post attachments' : null,
            ])->filter()->implode(' and ');

            if (!$this->confirm("Move existing {$targets} into opaque storage buckets?")) {
                $this->warn('Aborted.');
                return self::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->info('Dry run mode: no files or database records will be changed.');
        }

        $mediaStats = ['moved' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => 0];
        $attachmentStats = ['moved' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => 0];

        if ($migrateMedia) {
            $this->line('');
            $this->info('Migrating media library files...');
            $this->migrateMediaItems($dryRun, $mediaStats);
        }

        if ($migrateAttachments) {
            $this->line('');
            $this->info('Migrating post attachments...');
            $this->migratePostAttachments($dryRun, $attachmentStats);
        }

        $this->line('');
        $this->table(
            ['Target', 'Moved', 'Skipped', 'Missing', 'Errors'],
            array_values(array_filter([
                $migrateMedia ? ['Media', $mediaStats['moved'], $mediaStats['skipped'], $mediaStats['missing'], $mediaStats['errors']] : null,
                $migrateAttachments ? ['Attachments', $attachmentStats['moved'], $attachmentStats['skipped'], $attachmentStats['missing'], $attachmentStats['errors']] : null,
            ]))
        );

        return ($mediaStats['errors'] + $attachmentStats['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrateMediaItems(bool $dryRun, array &$stats): void
    {
        MediaItem::query()
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($dryRun, &$stats) {
                foreach ($items as $item) {
                    $disk = $item->storage_disk ?: 'public';

                    if (!$item->storage_path || filter_var((string) $item->storage_path, FILTER_VALIDATE_URL)) {
                        $stats['skipped']++;
                        continue;
                    }

                    $period = optional($item->created_at)->format('Y/m') ?: now()->format('Y/m');
                    $context = $this->sanitizeStorageContext($item->storage_context ?: 'uploads');
                    $storageBucket = $item->storage_bucket ?: $this->generateOpaqueBucket(
                        sprintf('media|entity:%s|context:%s|period:%s', $item->owner_entity_id, $context, $period)
                    );
                    $storageSuffixKey = $item->storage_suffix_key ?: $this->buildDeterministicSuffixKey(
                        'media',
                        (string) $item->id,
                        $item->created_at?->getTimestampMs()
                    );
                    $preferredName = $this->sanitizeOriginalFileName($item->original_name ?: $item->file_name ?: 'file');
                    $targetFileName = $this->buildStorageFileName($preferredName, $storageSuffixKey);
                    $targetPath = "media/{$storageBucket}/{$targetFileName}";

                    $currentThumbPath = $item->thumbnail_path ?: null;
                    $targetThumbPath = $currentThumbPath
                        ? $this->buildThumbnailPath($targetPath, pathinfo($currentThumbPath, PATHINFO_EXTENSION) ?: 'jpg')
                        : null;

                    if (
                        $item->storage_path === $targetPath
                        && $currentThumbPath === $targetThumbPath
                        && $item->storage_bucket === $storageBucket
                        && $item->storage_suffix_key === $storageSuffixKey
                    ) {
                        $stats['skipped']++;
                        continue;
                    }

                    if (!Storage::disk($disk)->exists($item->storage_path)) {
                        $this->warn("Missing media file for item #{$item->id}: {$item->storage_path}");
                        $stats['missing']++;
                        continue;
                    }

                    try {
                        if (Storage::disk($disk)->exists($targetPath) && $item->storage_path !== $targetPath) {
                            throw new \RuntimeException("Target path already exists: {$targetPath}");
                        }

                        $this->line(sprintf(
                            '[Media #%d] %s -> %s',
                            $item->id,
                            $item->storage_path,
                            $targetPath
                        ));

                        if (!$dryRun) {
                            if ($item->storage_path !== $targetPath) {
                                Storage::disk($disk)->makeDirectory(dirname($targetPath));
                                Storage::disk($disk)->move($item->storage_path, $targetPath);
                            }

                            if (
                                $currentThumbPath
                                && Storage::disk($disk)->exists($currentThumbPath)
                                && $currentThumbPath !== $targetThumbPath
                            ) {
                                Storage::disk($disk)->makeDirectory(dirname($targetThumbPath));
                                Storage::disk($disk)->move($currentThumbPath, $targetThumbPath);
                            }

                            $item->forceFill([
                                'storage_bucket' => $storageBucket,
                                'storage_suffix_key' => $storageSuffixKey,
                                'storage_path' => $targetPath,
                                'file_name' => basename($targetPath),
                                'public_url' => Storage::disk($disk)->url($targetPath),
                                'thumbnail_path' => $targetThumbPath,
                                'thumbnail_url' => $targetThumbPath ? Storage::disk($disk)->url($targetThumbPath) : null,
                            ])->save();
                        }

                        $stats['moved']++;
                    } catch (\Throwable $e) {
                        $this->error("Failed media item #{$item->id}: {$e->getMessage()}");
                        $stats['errors']++;
                    }
                }
            });
    }

    private function migratePostAttachments(bool $dryRun, array &$stats): void
    {
        PostAttachment::query()
            ->orderBy('id')
            ->chunkById(100, function ($attachments) use ($dryRun, &$stats) {
                foreach ($attachments as $attachment) {
                    if (!$attachment->attachment_uri || filter_var((string) $attachment->attachment_uri, FILTER_VALIDATE_URL)) {
                        $stats['skipped']++;
                        continue;
                    }

                    $storageBucket = $attachment->storage_bucket ?: $this->generateOpaqueBucket(
                        sprintf('post-attachments|post:%s', $attachment->post_id)
                    );
                    $storageSuffixKey = $attachment->storage_suffix_key ?: $this->buildDeterministicSuffixKey(
                        'attachment',
                        (string) $attachment->id,
                        $attachment->created_at?->getTimestampMs()
                    );
                    $preferredName = $this->sanitizeOriginalFileName(
                        $this->stripExistingStorageSuffix($attachment->file_name ?: $attachment->attachment_title ?: 'attachment')
                    );
                    $targetFileName = $this->buildStorageFileName($preferredName, $storageSuffixKey);
                    $targetPath = "post-attachments/{$storageBucket}/{$targetFileName}";

                    if (
                        $attachment->attachment_uri === $targetPath
                        && $attachment->storage_bucket === $storageBucket
                        && $attachment->storage_suffix_key === $storageSuffixKey
                        && $attachment->file_name === $preferredName
                    ) {
                        $stats['skipped']++;
                        continue;
                    }

                    if (!Storage::disk('public')->exists($attachment->attachment_uri)) {
                        $this->warn("Missing attachment file #{$attachment->id}: {$attachment->attachment_uri}");
                        $stats['missing']++;
                        continue;
                    }

                    try {
                        if (Storage::disk('public')->exists($targetPath) && $attachment->attachment_uri !== $targetPath) {
                            throw new \RuntimeException("Target path already exists: {$targetPath}");
                        }

                        $this->line(sprintf(
                            '[Attachment #%d] %s -> %s',
                            $attachment->id,
                            $attachment->attachment_uri,
                            $targetPath
                        ));

                        if (!$dryRun) {
                            if ($attachment->attachment_uri !== $targetPath) {
                                Storage::disk('public')->makeDirectory(dirname($targetPath));
                                Storage::disk('public')->move($attachment->attachment_uri, $targetPath);
                            }

                            $attachment->forceFill([
                                'storage_bucket' => $storageBucket,
                                'storage_suffix_key' => $storageSuffixKey,
                                'attachment_uri' => $targetPath,
                                'file_name' => $preferredName,
                            ])->save();
                        }

                        $stats['moved']++;
                    } catch (\Throwable $e) {
                        $this->error("Failed attachment #{$attachment->id}: {$e->getMessage()}");
                        $stats['errors']++;
                    }
                }
            });
    }

    private function generateOpaqueBucket(string $logicalKey): string
    {
        return substr(hash_hmac('sha256', $logicalKey, (string) config('media.storage_hash_key')), 0, 24);
    }

    private function sanitizeStorageContext(string $context): string
    {
        $segments = collect(explode('/', str_replace('\\', '/', $context)))
            ->filter()
            ->map(fn ($segment) => \Illuminate\Support\Str::slug($segment))
            ->filter()
            ->values();

        return $segments->isNotEmpty() ? $segments->implode('/') : 'uploads';
    }

    private function sanitizeOriginalFileName(string $originalName): string
    {
        $cleaned = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '-', $originalName) ?? '';
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? '';
        $cleaned = trim($cleaned, " .\t\n\r\0\x0B");

        return $cleaned !== '' ? $cleaned : 'file';
    }

    private function buildStorageFileName(string $originalName, string $storageSuffixKey): string
    {
        $baseName = trim(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeBase = $baseName !== '' ? $baseName : 'file';

        return $extension
            ? sprintf('%s--%s.%s', $safeBase, $storageSuffixKey, $extension)
            : sprintf('%s--%s', $safeBase, $storageSuffixKey);
    }

    private function buildThumbnailPath(string $storagePath, string $extension): string
    {
        $baseName = pathinfo($storagePath, PATHINFO_FILENAME);
        $sanitizedBase = Str::slug($baseName) ?: 'media';

        return dirname($storagePath) . "/thumbnails/{$sanitizedBase}_thumb.{$extension}";
    }

    private function stripExistingStorageSuffix(string $fileName): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $strippedBase = preg_replace('/--[0-9a-v]{12}$/i', '', $baseName) ?: $baseName;

        return $extension ? "{$strippedBase}.{$extension}" : $strippedBase;
    }

    private function buildDeterministicSuffixKey(string $type, string $id, ?int $timestampMs = null): string
    {
        $timestampPart = $this->timestampPart($timestampMs);
        $randomPart = substr($this->base32Digest("{$type}|{$id}"), 0, 6);

        return strtolower($timestampPart . $randomPart);
    }

    private function timestampPart(?int $timestampMs): string
    {
        $value = $timestampMs ?: (int) floor(microtime(true) * 1000);
        $encoded = $this->toBase32($value);

        return str_pad(substr($encoded, -6), 6, '0', STR_PAD_LEFT);
    }

    private function base32Digest(string $value): string
    {
        $binary = hash_hmac('sha256', $value, (string) config('media.storage_hash_key'), true);
        $alphabet = '0123456789abcdefghijklmnopqrstuv';
        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (str_split($binary) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $output .= $alphabet[($buffer >> $bits) & 31];
            }
        }

        if ($bits > 0) {
            $output .= $alphabet[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }

    private function toBase32(int $value): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuv';

        if ($value <= 0) {
            return '0';
        }

        $encoded = '';

        while ($value > 0) {
            $encoded = $alphabet[$value % 32] . $encoded;
            $value = intdiv($value, 32);
        }

        return $encoded;
    }
}
