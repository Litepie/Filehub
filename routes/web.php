<?php

use Illuminate\Support\Facades\Route;
use Litepie\FileHub\Controllers\FileController;
use Litepie\FileHub\Controllers\UploadController;

Route::group([
    'prefix' => config('filehub.urls.route_prefix', 'filehub'),
    'middleware' => config('filehub.urls.middleware', ['web']),
], function () {
    // File serving routes
    Route::get('/file/{attachment}', [FileController::class, 'serve'])
        ->name('filehub.file.serve');
    
    Route::get('/file/{attachment}/download', [FileController::class, 'download'])
        ->name('filehub.file.download');
    
    Route::get('/file/{attachment}/variant/{variant}', [FileController::class, 'variant'])
        ->name('filehub.file.variant');
});

// Protected upload routes with API middleware
Route::group([
    'prefix' => config('filehub.urls.route_prefix', 'filehub') . '/api',
    'middleware' => config('filehub.middleware.upload', ['api', 'throttle:60,1']),
], function () {
    // Generate upload token (requires API key)
    Route::post('/upload/token', [UploadController::class, 'generateUploadToken'])
        ->name('filehub.upload.token')
        ->middleware('filehub.validate_api_key');
    
    // Upload files (requires upload token or API key)
    Route::post('/upload', [UploadController::class, 'upload'])
        ->name('filehub.upload')
        ->middleware('throttle:' . config('filehub.security.max_uploads_per_minute', 10) . ',1');
    
    // Signed upload URL (validates signature)
    Route::post('/upload/signed', [UploadController::class, 'upload'])
        ->name('filehub.upload.signed')
        ->middleware('filehub.validate_signed_upload');
});
