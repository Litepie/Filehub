<?php

namespace Litepie\FileHub\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Litepie\FileHub\Services\FileHubManager;
use Litepie\FileHub\Services\UploadTokenManager;
use Litepie\FileHub\Exceptions\SecurityException;
use Litepie\FileHub\Exceptions\ValidationException;

class UploadController extends Controller
{
    public function __construct(
        private FileHubManager $fileManager,
        private UploadTokenManager $tokenManager
    ) {
        $this->middleware('throttle:' . config('filehub.security.max_uploads_per_minute', 10) . ',1');
    }

    public function generateUploadToken(Request $request): JsonResponse
    {
        $this->validateApiKey($request);

        $validator = Validator::make($request->all(), [
            'collection' => 'string|max:255',
            'max_files' => 'integer|min:1|max:' . config('filehub.validation.max_files_per_request', 10),
            'allowed_mimes' => 'array',
            'max_size' => 'integer|min:1',
            'expires_in' => 'integer|min:60|max:86400', // 1 minute to 24 hours
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tokenData = [
            'collection' => $request->get('collection', 'default'),
            'max_files' => $request->get('max_files', 1),
            'allowed_mimes' => $request->get('allowed_mimes'),
            'max_size' => $request->get('max_size'),
            'expires_in' => $request->get('expires_in', config('filehub.security.upload_token_expiry', 3600)),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        $token = $this->tokenManager->generateToken($tokenData);

        return response()->json([
            'upload_token' => $token,
            'expires_at' => now()->addSeconds($tokenData['expires_in'])->toISOString(),
            'upload_url' => route('filehub.upload'),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        try {
            // Rate limiting per IP
            $this->checkRateLimit($request);

            // Validate upload token if required
            if (config('filehub.security.require_upload_token', true)) {
                $this->validateUploadToken($request);
            } else {
                $this->validateApiKey($request);
            }

            // Validate the upload request
            $validator = Validator::make($request->all(), [
                'files' => 'required|array|min:1',
                'files.*' => 'required|file',
                'model_type' => 'required|string',
                'model_id' => 'required|string',
                'collection' => 'string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the model instance
            $modelClass = $request->get('model_type');
            $modelId = $request->get('model_id');
            
            if (!class_exists($modelClass)) {
                throw new ValidationException('Invalid model type');
            }

            $model = $modelClass::find($modelId);
            if (!$model) {
                throw new ValidationException('Model not found');
            }

            // Upload files
            $files = $request->file('files');
            $collection = $request->get('collection', 'default');
            $options = $this->getUploadOptions($request);

            $attachments = $this->fileManager->attach($model, $files, $collection, $options);

            // Format response
            $response = is_array($attachments) 
                ? array_map(fn($attachment) => $this->formatAttachment($attachment), $attachments)
                : [$this->formatAttachment($attachments)];

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
                'attachments' => $response,
            ]);

        } catch (SecurityException $e) {
            return response()->json([
                'error' => 'Security violation',
                'message' => $e->getMessage()
            ], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed',
                'message' => 'An error occurred while uploading files'
            ], 500);
        }
    }

    private function validateApiKey(Request $request): void
    {
        $apiKey = config('filehub.security.upload_api_key');
        
        if (!$apiKey) {
            throw new SecurityException('Upload API key not configured');
        }

        $providedKey = $request->header('X-FileHub-API-Key') ?? $request->get('api_key');
        
        if (!$providedKey || !hash_equals($apiKey, $providedKey)) {
            throw new SecurityException('Invalid or missing API key');
        }
    }

    private function validateUploadToken(Request $request): void
    {
        $token = $request->header('X-Upload-Token') ?? $request->get('upload_token');
        
        if (!$token) {
            throw new SecurityException('Upload token required');
        }

        $tokenData = $this->tokenManager->validateToken($token);
        
        if (!$tokenData) {
            throw new SecurityException('Invalid or expired upload token');
        }

        // Validate IP address if specified in token
        if (isset($tokenData['ip_address']) && $tokenData['ip_address'] !== $request->ip()) {
            throw new SecurityException('Token IP address mismatch');
        }

        // Store token data in request for later validation
        $request->merge(['_token_data' => $tokenData]);
    }

    private function checkRateLimit(Request $request): void
    {
        $key = 'filehub_upload:' . $request->ip();
        $maxAttempts = config('filehub.security.max_uploads_per_hour', 100);
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw new SecurityException("Too many upload attempts. Try again in {$seconds} seconds.");
        }

        RateLimiter::hit($key, 3600); // 1 hour decay
    }

    private function getUploadOptions(Request $request): array
    {
        $options = [];
        
        // If using upload token, apply token restrictions
        if ($tokenData = $request->get('_token_data')) {
            if (isset($tokenData['allowed_mimes'])) {
                $options['allowed_mimes'] = $tokenData['allowed_mimes'];
            }
            if (isset($tokenData['max_size'])) {
                $options['max_size'] = $tokenData['max_size'];
            }
        }

        return $options;
    }

    private function formatAttachment($attachment): array
    {
        return [
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'original_filename' => $attachment->original_filename,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'human_readable_size' => $attachment->human_readable_size,
            'file_type' => $attachment->file_type,
            'collection' => $attachment->collection,
            'url' => $attachment->url,
            'download_url' => $attachment->download_url,
            'variants' => $attachment->hasVariants() ? array_keys($attachment->getVariants()) : [],
            'created_at' => $attachment->created_at->toISOString(),
            'uploader' => [
                'name' => $attachment->uploader_name,
                'ip_address' => $attachment->upload_ip_address,
                'uploaded_at' => $attachment->created_at->toISOString(),
            ],
        ];
    }
}
