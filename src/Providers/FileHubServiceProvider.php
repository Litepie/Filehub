<?php

namespace Litepie\FileHub\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Litepie\FileHub\Contracts\FileHubManagerContract;
use Litepie\FileHub\Services\FileHubManager;
use Litepie\FileHub\Services\ImageProcessor;
use Litepie\FileHub\Services\SecurityValidator;
use Litepie\FileHub\Services\FileTypeDetector;
use Litepie\FileHub\Services\UploadTokenManager;
use Litepie\FileHub\Services\FileUploaderService;
use Litepie\FileHub\Commands\CleanupOrphanedFiles;
use Litepie\FileHub\Commands\RegenerateVariants;
use Litepie\FileHub\Middleware\ValidateUploadApiKey;

class FileHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/filehub.php',
            'filehub'
        );

        $this->app->singleton(FileHubManagerContract::class, FileHubManager::class);
        $this->app->singleton(ImageProcessor::class);
        $this->app->singleton(SecurityValidator::class);
        $this->app->singleton(FileTypeDetector::class);
        $this->app->singleton(UploadTokenManager::class);
        $this->app->singleton(FileUploaderService::class);
        
        $this->app->alias(FileHubManagerContract::class, 'filehub');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/filehub.php' => config_path('filehub.php'),
            ], 'filehub-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'filehub-migrations');

            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/filehub'),
            ], 'filehub-views');

            $this->commands([
                CleanupOrphanedFiles::class,
                RegenerateVariants::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'filehub');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Register middleware
        $this->registerMiddleware();

        // Register validation rules
        $this->registerValidationRules();
    }

    private function registerValidationRules(): void
    {
        $this->app['validator']->extend('filehub_secure_file', function ($attribute, $value, $parameters, $validator) {
            try {
                app(SecurityValidator::class)->validate($value);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        
        $router->aliasMiddleware('filehub.validate_api_key', ValidateUploadApiKey::class);
    }
}
