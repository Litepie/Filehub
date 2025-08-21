<?php

namespace Litepie\FileHub\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UploadTokenManager
{
    private string $cachePrefix = 'filehub_upload_token:';
    
    public function generateToken(array $data): string
    {
        $token = Str::random(64);
        $expiresIn = $data['expires_in'] ?? config('filehub.security.upload_token_expiry', 3600);
        
        $tokenData = array_merge($data, [
            'token' => $token,
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds($expiresIn)->toISOString(),
            'used' => false,
        ]);
        
        Cache::put(
            $this->cachePrefix . $token,
            $tokenData,
            $expiresIn
        );
        
        return $token;
    }
    
    public function validateToken(string $token): ?array
    {
        $tokenData = Cache::get($this->cachePrefix . $token);
        
        if (!$tokenData) {
            return null;
        }
        
        // Check if token has expired
        if (Carbon::parse($tokenData['expires_at'])->isPast()) {
            $this->revokeToken($token);
            return null;
        }
        
        return $tokenData;
    }
    
    public function useToken(string $token): bool
    {
        $tokenData = $this->validateToken($token);
        
        if (!$tokenData) {
            return false;
        }
        
        // Mark token as used (for single-use tokens if needed)
        $tokenData['used'] = true;
        $tokenData['used_at'] = now()->toISOString();
        
        $remainingTtl = Carbon::parse($tokenData['expires_at'])->diffInSeconds(now());
        
        Cache::put(
            $this->cachePrefix . $token,
            $tokenData,
            $remainingTtl
        );
        
        return true;
    }
    
    public function revokeToken(string $token): bool
    {
        return Cache::forget($this->cachePrefix . $token);
    }
    
    public function generateSignedUploadUrl(array $parameters = []): string
    {
        $timestamp = now()->timestamp;
        $expires = $timestamp + config('filehub.security.upload_token_expiry', 3600);
        
        $payload = array_merge($parameters, [
            'timestamp' => $timestamp,
            'expires' => $expires,
        ]);
        
        $signature = $this->generateSignature($payload);
        $payload['signature'] = $signature;
        
        return route('filehub.upload.signed', $payload);
    }
    
    public function validateSignedUploadUrl(array $parameters): bool
    {
        if (!isset($parameters['signature'], $parameters['expires'])) {
            return false;
        }
        
        // Check if URL has expired
        if ($parameters['expires'] < now()->timestamp) {
            return false;
        }
        
        $signature = $parameters['signature'];
        unset($parameters['signature']);
        
        $expectedSignature = $this->generateSignature($parameters);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    private function generateSignature(array $payload): string
    {
        ksort($payload);
        $data = http_build_query($payload);
        $secret = config('filehub.security.upload_api_key') ?? config('app.key');
        
        return hash_hmac('sha256', $data, $secret);
    }
    
    public function getTokenInfo(string $token): ?array
    {
        return Cache::get($this->cachePrefix . $token);
    }
    
    public function extendToken(string $token, int $additionalSeconds): bool
    {
        $tokenData = $this->validateToken($token);
        
        if (!$tokenData) {
            return false;
        }
        
        $newExpiresAt = Carbon::parse($tokenData['expires_at'])->addSeconds($additionalSeconds);
        $tokenData['expires_at'] = $newExpiresAt->toISOString();
        
        $newTtl = $newExpiresAt->diffInSeconds(now());
        
        Cache::put(
            $this->cachePrefix . $token,
            $tokenData,
            $newTtl
        );
        
        return true;
    }
    
    public function cleanupExpiredTokens(): int
    {
        // This would typically be handled by cache expiration
        // But you could implement manual cleanup if needed
        return 0;
    }
}
