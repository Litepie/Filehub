<?php

use Illuminate\Support\Facades\Route;
use Litepie\FileHub\Controllers\FileUploaderController;

// User upload tracking routes (require authentication)
Route::group([
    'prefix' => config('filehub.urls.route_prefix', 'filehub') . '/api',
    'middleware' => config('filehub.middleware.auth', ['api', 'auth']),
], function () {
    // User's own uploads
    Route::get('/my-uploads', [FileUploaderController::class, 'myUploads'])
        ->name('filehub.my_uploads');
    
    Route::get('/my-stats', [FileUploaderController::class, 'myStats'])
        ->name('filehub.my_stats');
    
    Route::get('/my-quota', [FileUploaderController::class, 'quota'])
        ->name('filehub.my_quota');
});

// Admin routes for user tracking (require admin permission)
Route::group([
    'prefix' => config('filehub.urls.route_prefix', 'filehub') . '/api/admin',
    'middleware' => config('filehub.middleware.admin', ['api', 'auth']),
], function () {
    // User management
    Route::get('/users/{userId}/uploads', [FileUploaderController::class, 'userUploads'])
        ->name('filehub.admin.user_uploads');
    
    Route::get('/uploaders', [FileUploaderController::class, 'uploaders'])
        ->name('filehub.admin.uploaders');
    
    // Upload monitoring
    Route::get('/recent-uploads', [FileUploaderController::class, 'recentUploads'])
        ->name('filehub.admin.recent_uploads');
    
    Route::get('/upload-activity', [FileUploaderController::class, 'uploadActivity'])
        ->name('filehub.admin.upload_activity');
    
    // Security tracking
    Route::get('/ip/{ipAddress}/files', [FileUploaderController::class, 'filesByIp'])
        ->name('filehub.admin.files_by_ip');
    
    Route::get('/duplicates', [FileUploaderController::class, 'duplicates'])
        ->name('filehub.admin.duplicates');
});
