// routes/api.php or routes/web.php

use App\Http\Controllers\FileHubController;

// Using configurable middleware from FileHub config
Route::middleware(config('filehub.middleware.auth', ['auth:sanctum']))
    ->prefix('api/filehub')
    ->group(function () {
        // Generate upload token
        Route::post('/token', [FileHubController::class, 'generateToken']);
        
        // File upload
        Route::post('/upload', [FileHubController::class, 'upload']);
        
        // File management
        Route::put('/files/{id}/metadata', [FileHubController::class, 'updateMetadata']);
        Route::delete('/files/{id}', [FileHubController::class, 'delete']);
        Route::post('/files/reorder', [FileHubController::class, 'reorder']);
        
        // File listing
        Route::get('/files', [FileHubController::class, 'list']);
    });

// Admin routes with configurable admin middleware
Route::middleware(config('filehub.middleware.admin', ['auth:sanctum', 'role:admin']))
    ->prefix('api/filehub/admin')
    ->group(function () {
        // Admin specific routes
        Route::get('/users/{user}/files', [FileHubController::class, 'userFiles']);
        Route::get('/statistics', [FileHubController::class, 'statistics']);
    });

// Public routes (if needed)
Route::prefix('api/filehub')->group(function () {
    // For guest uploads (if you allow them)
    Route::post('/guest/token', [FileHubController::class, 'generateGuestToken']);
    Route::post('/guest/upload', [FileHubController::class, 'guestUpload']);
});
