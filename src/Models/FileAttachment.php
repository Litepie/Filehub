<?php

namespace Litepie\FileHub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class FileAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'collection',
        'filename',
        'original_filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'metadata',
        'variants',
        'file_hash',
        'uploaded_by',
        'uploaded_by_type',
        'upload_ip_address',
        'upload_user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'variants' => 'array',
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'url',
        'download_url',
        'human_readable_size',
        'file_type',
        'uploader_name',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): MorphTo
    {
        return $this->morphTo('uploaded_by');
    }

    /**
     * Get the URL for the file (method version)
     */
    public function url(string $variant = null): string
    {
        if ($variant) {
            return $this->getVariantUrl($variant) ?? $this->getUrlAttribute();
        }
        
        return $this->getUrlAttribute();
    }

    public function getUrlAttribute(): string
    {
        if (config('filehub.urls.signed', false)) {
            return $this->getSignedUrl();
        }

        try {
            return Storage::disk($this->disk)->url($this->path);
        } catch (\Exception $e) {
            return route('filehub.file.serve', ['attachment' => $this->id]);
        }
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('filehub.file.download', ['attachment' => $this->id]);
    }

    public function getSignedUrl(int $expiration = null): string
    {
        $expiration = $expiration ?? config('filehub.urls.expiration', 3600);
        
        return URL::temporarySignedRoute(
            'filehub.file.serve',
            now()->addSeconds($expiration),
            ['attachment' => $this->id]
        );
    }

    public function getVariantUrl(string $variant): ?string
    {
        if (!isset($this->variants[$variant])) {
            return null;
        }

        if (config('filehub.urls.signed', false)) {
            return URL::temporarySignedRoute(
                'filehub.file.variant',
                now()->addSeconds(config('filehub.urls.expiration', 3600)),
                ['attachment' => $this->id, 'variant' => $variant]
            );
        }

        try {
            return Storage::disk($this->disk)->url($this->variants[$variant]['path']);
        } catch (\Exception $e) {
            return route('filehub.file.variant', [
                'attachment' => $this->id,
                'variant' => $variant
            ]);
        }
    }

    public function getHumanReadableSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileTypeAttribute(): string
    {
        return match (true) {
            str_starts_with($this->mime_type, 'image/') => 'image',
            str_starts_with($this->mime_type, 'video/') => 'video',
            str_starts_with($this->mime_type, 'audio/') => 'audio',
            in_array($this->mime_type, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ]) => 'document',
            default => 'file'
        };
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    public function isDocument(): bool
    {
        return $this->file_type === 'document';
    }

    public function hasVariants(): bool
    {
        return !empty($this->variants);
    }

    public function getVariants(): array
    {
        return $this->variants ?? [];
    }

    public function getVariant(string $name): ?array
    {
        return $this->variants[$name] ?? null;
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function getContents(): string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    public function getStream()
    {
        return Storage::disk($this->disk)->readStream($this->path);
    }

    public function getDimensions(): ?array
    {
        return $this->metadata['dimensions'] ?? null;
    }

    public function getWidth(): ?int
    {
        return $this->getDimensions()['width'] ?? null;
    }

    public function getHeight(): ?int
    {
        return $this->getDimensions()['height'] ?? null;
    }

    public function getAspectRatio(): ?float
    {
        return $this->getDimensions()['aspect_ratio'] ?? null;
    }

    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    public function scopeOfType($query, string $type)
    {
        return match ($type) {
            'image' => $query->where('mime_type', 'like', 'image/%'),
            'video' => $query->where('mime_type', 'like', 'video/%'),
            'audio' => $query->where('mime_type', 'like', 'audio/%'),
            'document' => $query->whereIn('mime_type', [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ]),
            default => $query
        };
    }

    public function scopeImages($query)
    {
        return $query->ofType('image');
    }

    public function scopeVideos($query)
    {
        return $query->ofType('video');
    }

    public function scopeDocuments($query)
    {
        return $query->ofType('document');
    }

    public function scopeUploadedBy($query, $user)
    {
        if ($user instanceof Model) {
            return $query->where('uploaded_by', $user->getKey())
                         ->where('uploaded_by_type', get_class($user));
        }
        
        return $query->where('uploaded_by', $user);
    }

    public function scopeUploadedByType($query, string $type)
    {
        return $query->where('uploaded_by_type', $type);
    }

    public function scopeUploadedFromIp($query, string $ip)
    {
        return $query->where('upload_ip_address', $ip);
    }

    public function getUploaderNameAttribute(): ?string
    {
        if (!$this->uploadedBy) {
            return null;
        }

        // Try common name attributes
        $nameAttributes = ['name', 'display_name', 'full_name', 'username', 'email'];
        
        foreach ($nameAttributes as $attribute) {
            if (isset($this->uploadedBy->$attribute)) {
                return $this->uploadedBy->$attribute;
            }
        }

        return "User #{$this->uploaded_by}";
    }

    public function hasUploader(): bool
    {
        return !is_null($this->uploaded_by) && !is_null($this->uploaded_by_type);
    }
}
