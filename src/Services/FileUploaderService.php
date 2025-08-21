<?php

namespace Litepie\FileHub\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Litepie\FileHub\Models\FileAttachment;

class FileUploaderService
{
    /**
     * Get all files uploaded by a specific user
     */
    public function getFilesByUploader(Model $user, array $options = []): Collection
    {
        $query = FileAttachment::uploadedBy($user);

        // Apply filters
        if (isset($options['collection'])) {
            $query->inCollection($options['collection']);
        }

        if (isset($options['file_type'])) {
            $query->ofType($options['file_type']);
        }

        if (isset($options['date_from'])) {
            $query->where('created_at', '>=', $options['date_from']);
        }

        if (isset($options['date_to'])) {
            $query->where('created_at', '<=', $options['date_to']);
        }

        // Apply ordering
        $orderBy = $options['order_by'] ?? 'created_at';
        $orderDirection = $options['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        // Apply pagination if requested
        if (isset($options['paginate'])) {
            return $query->paginate($options['paginate']);
        }

        return $query->get();
    }

    /**
     * Get upload statistics for a user
     */
    public function getUploaderStats(Model $user): array
    {
        $baseQuery = FileAttachment::uploadedBy($user);

        return [
            'total_files' => $baseQuery->count(),
            'total_size' => $baseQuery->sum('size'),
            'files_by_type' => $baseQuery
                ->select('mime_type', DB::raw('count(*) as count'), DB::raw('sum(size) as total_size'))
                ->groupBy('mime_type')
                ->get()
                ->map(function ($item) {
                    $item->file_type = $this->getFileTypeFromMime($item->mime_type);
                    return $item;
                }),
            'files_by_collection' => $baseQuery
                ->select('collection', DB::raw('count(*) as count'))
                ->groupBy('collection')
                ->get(),
            'upload_timeline' => $baseQuery
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get(),
        ];
    }

    /**
     * Get all uploaders (users who have uploaded files)
     */
    public function getAllUploaders(): Collection
    {
        return FileAttachment::select('uploaded_by', 'uploaded_by_type')
            ->whereNotNull('uploaded_by')
            ->whereNotNull('uploaded_by_type')
            ->groupBy('uploaded_by', 'uploaded_by_type')
            ->with('uploadedBy')
            ->get()
            ->map(function ($attachment) {
                return [
                    'user' => $attachment->uploadedBy,
                    'user_type' => $attachment->uploaded_by_type,
                    'file_count' => FileAttachment::uploadedBy($attachment->uploadedBy)->count(),
                    'total_size' => FileAttachment::uploadedBy($attachment->uploadedBy)->sum('size'),
                    'last_upload' => FileAttachment::uploadedBy($attachment->uploadedBy)
                        ->orderBy('created_at', 'desc')
                        ->value('created_at'),
                ];
            });
    }

    /**
     * Get files uploaded from a specific IP address
     */
    public function getFilesByIpAddress(string $ipAddress): Collection
    {
        return FileAttachment::uploadedFromIp($ipAddress)->get();
    }

    /**
     * Get recent uploads across all users
     */
    public function getRecentUploads(int $limit = 50): Collection
    {
        return FileAttachment::whereNotNull('uploaded_by')
            ->with('uploadedBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get upload activity for a date range
     */
    public function getUploadActivity(string $dateFrom, string $dateTo): array
    {
        $files = FileAttachment::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('uploaded_by')
            ->with('uploadedBy')
            ->get();

        return [
            'total_uploads' => $files->count(),
            'unique_uploaders' => $files->unique('uploaded_by')->count(),
            'total_size' => $files->sum('size'),
            'uploads_by_day' => $files->groupBy(function ($file) {
                return $file->created_at->format('Y-m-d');
            })->map->count(),
            'uploads_by_user' => $files->groupBy('uploaded_by')->map(function ($userFiles) {
                $firstFile = $userFiles->first();
                return [
                    'user' => $firstFile->uploadedBy,
                    'count' => $userFiles->count(),
                    'total_size' => $userFiles->sum('size'),
                ];
            }),
        ];
    }

    /**
     * Find files that may be duplicates (same hash, different uploaders)
     */
    public function findPotentialDuplicates(): Collection
    {
        return FileAttachment::select('file_hash', DB::raw('count(*) as count'))
            ->whereNotNull('file_hash')
            ->groupBy('file_hash')
            ->having('count', '>', 1)
            ->get()
            ->map(function ($item) {
                return [
                    'file_hash' => $item->file_hash,
                    'duplicate_count' => $item->count,
                    'files' => FileAttachment::where('file_hash', $item->file_hash)
                        ->with('uploadedBy')
                        ->get(),
                ];
            });
    }

    /**
     * Get user upload quota information
     */
    public function getUserQuotaInfo(Model $user, array $quotaConfig = []): array
    {
        $files = FileAttachment::uploadedBy($user);
        $totalSize = $files->sum('size');
        $totalFiles = $files->count();

        $maxSize = $quotaConfig['max_size'] ?? config('filehub.validation.max_size', 10240) * 1024; // Convert KB to bytes
        $maxFiles = $quotaConfig['max_files'] ?? 1000;

        return [
            'used_size' => $totalSize,
            'used_files' => $totalFiles,
            'max_size' => $maxSize,
            'max_files' => $maxFiles,
            'size_percentage' => $maxSize > 0 ? round(($totalSize / $maxSize) * 100, 2) : 0,
            'files_percentage' => $maxFiles > 0 ? round(($totalFiles / $maxFiles) * 100, 2) : 0,
            'can_upload' => $totalSize < $maxSize && $totalFiles < $maxFiles,
        ];
    }

    private function getFileTypeFromMime(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ]) => 'document',
            default => 'file'
        };
    }
}
