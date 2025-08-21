<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Litepie\FileHub\Facades\FileHub;
use Litepie\FileHub\Facades\UploadToken;

class FileHubController extends Controller
{
    /**
     * Generate upload token for the file uploader
     */
    public function generateToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'collection' => 'nullable|string|max:255',
            'max_files' => 'nullable|integer|min:1|max:50',
            'max_size' => 'nullable|integer|min:1024', // KB
            'allowed_mimes' => 'nullable|array',
            'expires_in' => 'nullable|integer|min:300|max:86400' // 5 min to 24 hours
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $config = [
                'collection' => $request->get('collection', 'default'),
                'max_files' => $request->get('max_files', 10),
                'max_size' => $request->get('max_size', 5120), // KB
                'expires_in' => $request->get('expires_in', 3600), // 1 hour
            ];

            if ($request->has('allowed_mimes')) {
                $config['allowed_mimes'] = $request->get('allowed_mimes');
            }

            $token = UploadToken::generateToken($config);

            return response()->json([
                'success' => true,
                'token' => $token,
                'config' => $config
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle file upload with metadata
     */
    public function upload(Request $request): JsonResponse
    {
        // Validate upload token first
        $tokenData = UploadToken::validateToken($request->get('upload_token'));
        
        if (!$tokenData) {
            return response()->json([
                'error' => 'Invalid or expired upload token'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'collection' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000',
            'upload_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the model to attach files to
            // This could be the authenticated user, or another model based on your needs
            $model = $this->getTargetModel($request);
            
            if (!$model) {
                return response()->json([
                    'error' => 'Target model not found'
                ], 404);
            }

            // Attach the file
            $attachment = FileHub::attach(
                $model,
                $request->file('file'),
                $request->get('collection'),
                [
                    'title' => $request->get('title'),
                    'caption' => $request->get('caption')
                ]
            );

            // Update metadata with title and caption
            $metadata = $attachment->metadata ?? [];
            
            if ($request->filled('title')) {
                $metadata['title'] = $request->get('title');
            }
            
            if ($request->filled('caption')) {
                $metadata['caption'] = $request->get('caption');
            }
            
            $attachment->update(['metadata' => $metadata]);

            return response()->json([
                'success' => true,
                'file' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'original_filename' => $attachment->original_filename,
                    'url' => $attachment->url,
                    'download_url' => $attachment->download_url,
                    'thumbnail_url' => $attachment->getVariantUrl('thumbnail'),
                    'small_url' => $attachment->getVariantUrl('small'),
                    'medium_url' => $attachment->getVariantUrl('medium'),
                    'title' => $metadata['title'] ?? null,
                    'caption' => $metadata['caption'] ?? null,
                    'size' => $attachment->size,
                    'human_size' => $attachment->human_readable_size,
                    'mime_type' => $attachment->mime_type,
                    'file_type' => $attachment->file_type,
                    'collection' => $attachment->collection,
                    'dimensions' => $attachment->getDimensions(),
                    'created_at' => $attachment->created_at,
                    'uploader' => $attachment->uploader_name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update file metadata (title, caption)
     */
    public function updateMetadata(Request $request, int $attachmentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attachment = \Litepie\FileHub\Models\FileAttachment::findOrFail($attachmentId);
            
            // Check if user has permission to edit this file
            if (!$this->canEditAttachment($attachment, $request)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $metadata = $attachment->metadata ?? [];
            
            if ($request->has('title')) {
                $metadata['title'] = $request->get('title');
            }
            
            if ($request->has('caption')) {
                $metadata['caption'] = $request->get('caption');
            }
            
            $attachment->update(['metadata' => $metadata]);

            return response()->json([
                'success' => true,
                'file' => [
                    'id' => $attachment->id,
                    'title' => $metadata['title'] ?? null,
                    'caption' => $metadata['caption'] ?? null,
                    'updated_at' => $attachment->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an uploaded file
     */
    public function delete(Request $request, int $attachmentId): JsonResponse
    {
        try {
            $attachment = \Litepie\FileHub\Models\FileAttachment::findOrFail($attachmentId);
            
            // Check if user has permission to delete this file
            if (!$this->canEditAttachment($attachment, $request)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $model = $attachment->attachable;
            
            if ($model && FileHub::detach($model, $attachmentId)) {
                return response()->json(['success' => true]);
            }

            return response()->json(['error' => 'Failed to delete file'], 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder files in a collection
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array',
            'file_ids.*' => 'integer|exists:file_attachments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $fileIds = $request->get('file_ids');
            
            foreach ($fileIds as $index => $fileId) {
                \Litepie\FileHub\Models\FileAttachment::where('id', $fileId)
                    ->update(['sort_order' => $index + 1]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Reorder failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get files for a specific model and collection
     */
    public function list(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'collection' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $modelType = $request->get('model_type');
            $modelId = $request->get('model_id');
            $collection = $request->get('collection');

            $query = \Litepie\FileHub\Models\FileAttachment::where('attachable_type', $modelType)
                ->where('attachable_id', $modelId);

            if ($collection) {
                $query->where('collection', $collection);
            }

            $attachments = $query->orderBy('sort_order', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $files = $attachments->map(function ($attachment) {
                $metadata = $attachment->metadata ?? [];
                
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'original_filename' => $attachment->original_filename,
                    'url' => $attachment->url,
                    'thumbnail_url' => $attachment->getVariantUrl('thumbnail'),
                    'title' => $metadata['title'] ?? null,
                    'caption' => $metadata['caption'] ?? null,
                    'size' => $attachment->size,
                    'human_size' => $attachment->human_readable_size,
                    'mime_type' => $attachment->mime_type,
                    'file_type' => $attachment->file_type,
                    'collection' => $attachment->collection,
                    'created_at' => $attachment->created_at,
                    'uploader' => $attachment->uploader_name
                ];
            });

            return response()->json([
                'success' => true,
                'files' => $files,
                'count' => $files->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'List failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine the target model for file attachment
     * Override this method based on your application's needs
     */
    protected function getTargetModel(Request $request)
    {
        // Default: attach to authenticated user
        if (auth()->check()) {
            return auth()->user();
        }

        // You can implement custom logic here, such as:
        // - Getting model from request parameters
        // - Using session data
        // - Creating a temporary model
        
        return null;
    }

    /**
     * Check if user can edit/delete an attachment
     * Override this method based on your application's authorization logic
     */
    protected function canEditAttachment($attachment, Request $request): bool
    {
        // Default: only the uploader or admin can edit
        if (auth()->check()) {
            $user = auth()->user();
            
            // Check if user is the uploader
            if ($attachment->uploaded_by === $user->id) {
                return true;
            }
            
            // Check if user is admin (implement your own admin check)
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }
        }

        return false;
    }
}
