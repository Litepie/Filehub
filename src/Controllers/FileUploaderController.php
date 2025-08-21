<?php

namespace Litepie\FileHub\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Litepie\FileHub\Models\FileAttachment;
use Litepie\FileHub\Services\FileUploaderService;

class FileUploaderController extends Controller
{
    public function __construct(
        private FileUploaderService $fileUploaderService
    ) {}

    /**
     * Get files uploaded by the current user
     */
    public function myUploads(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $options = [
            'collection' => $request->get('collection'),
            'file_type' => $request->get('file_type'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'order_by' => $request->get('order_by', 'created_at'),
            'order_direction' => $request->get('order_direction', 'desc'),
        ];

        if ($request->has('paginate')) {
            $options['paginate'] = $request->get('paginate', 15);
        }

        $files = $this->fileUploaderService->getFilesByUploader($user, $options);

        return response()->json([
            'success' => true,
            'files' => $files,
        ]);
    }

    /**
     * Get upload statistics for the current user
     */
    public function myStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $stats = $this->fileUploaderService->getUploaderStats($user);

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Get files uploaded by a specific user (admin only)
     */
    public function userUploads(Request $request, int $userId): JsonResponse
    {
        // Add your authorization logic here
        if (!$this->canViewUserUploads()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // You'll need to modify this based on your User model
        $userClass = config('auth.providers.users.model', 'App\\Models\\User');
        $user = $userClass::findOrFail($userId);

        $options = [
            'collection' => $request->get('collection'),
            'file_type' => $request->get('file_type'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'order_by' => $request->get('order_by', 'created_at'),
            'order_direction' => $request->get('order_direction', 'desc'),
        ];

        if ($request->has('paginate')) {
            $options['paginate'] = $request->get('paginate', 15);
        }

        $files = $this->fileUploaderService->getFilesByUploader($user, $options);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? $user->email,
            ],
            'files' => $files,
        ]);
    }

    /**
     * Get all users who have uploaded files (admin only)
     */
    public function uploaders(Request $request): JsonResponse
    {
        if (!$this->canViewUserUploads()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $uploaders = $this->fileUploaderService->getAllUploaders();

        return response()->json([
            'success' => true,
            'uploaders' => $uploaders,
        ]);
    }

    /**
     * Get recent uploads across all users (admin only)
     */
    public function recentUploads(Request $request): JsonResponse
    {
        if (!$this->canViewUserUploads()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $limit = $request->get('limit', 50);
        $files = $this->fileUploaderService->getRecentUploads($limit);

        return response()->json([
            'success' => true,
            'files' => $files,
        ]);
    }

    /**
     * Get upload activity report (admin only)
     */
    public function uploadActivity(Request $request): JsonResponse
    {
        if (!$this->canViewUserUploads()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $activity = $this->fileUploaderService->getUploadActivity($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'activity' => $activity,
        ]);
    }

    /**
     * Get files uploaded from a specific IP address (admin only)
     */
    public function filesByIp(Request $request, string $ipAddress): JsonResponse
    {
        if (!$this->canViewUserUploads()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $files = $this->fileUploaderService->getFilesByIpAddress($ipAddress);

        return response()->json([
            'success' => true,
            'ip_address' => $ipAddress,
            'files' => $files,
        ]);
    }

    /**
     * Find potential duplicate files (admin only)
     */
    public function duplicates(Request $request): JsonResponse
    {
        if (!$this->canViewUserUploads()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $duplicates = $this->fileUploaderService->findPotentialDuplicates();

        return response()->json([
            'success' => true,
            'duplicates' => $duplicates,
        ]);
    }

    /**
     * Get quota information for the current user
     */
    public function quota(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // You can customize quota config based on user roles/plans
        $quotaConfig = [
            'max_size' => 100 * 1024 * 1024, // 100MB
            'max_files' => 1000,
        ];

        $quotaInfo = $this->fileUploaderService->getUserQuotaInfo($user, $quotaConfig);

        return response()->json([
            'success' => true,
            'quota' => $quotaInfo,
        ]);
    }

    /**
     * Check if current user can view other users' uploads
     * You should implement your own authorization logic here
     */
    private function canViewUserUploads(): bool
    {
        $user = Auth::user();
        
        // Example: Check if user is admin
        // You'll need to implement this based on your authorization system
        return $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
    }
}
