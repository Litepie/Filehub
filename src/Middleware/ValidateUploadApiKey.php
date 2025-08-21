<?php

namespace Litepie\FileHub\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Litepie\FileHub\Exceptions\SecurityException;

class ValidateUploadApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('filehub.security.upload_api_key');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'Upload API key not configured'
            ], 500);
        }

        $providedKey = $request->header('X-FileHub-API-Key') ?? $request->get('api_key');
        
        if (!$providedKey || !hash_equals($apiKey, $providedKey)) {
            return response()->json([
                'error' => 'Invalid or missing API key'
            ], 403);
        }

        return $next($request);
    }
}
