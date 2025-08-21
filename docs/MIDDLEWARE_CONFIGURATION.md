# Middleware Configuration Guide

The FileHub package supports configurable middleware to allow for different authorization patterns depending on your application's needs.

## Configuration

The middleware configurations are defined in the `config/filehub.php` file under the `middleware` section:

```php
'middleware' => [
    // Base middleware for all FileHub routes
    'base' => ['api'],
    
    // Authentication middleware for user routes
    'auth' => ['api', 'auth'],
    
    // Admin middleware for admin routes
    'admin' => ['api', 'auth'], // Add your admin middleware here
    
    // Upload middleware for file upload routes
    'upload' => ['api', 'auth', 'throttle:uploads'],
],
```

## Customizing Middleware

### For Basic Authentication

If you're using Laravel's default authentication:

```php
'middleware' => [
    'auth' => ['api', 'auth'],
    'admin' => ['api', 'auth'],
],
```

### For Sanctum Authentication

If you're using Laravel Sanctum:

```php
'middleware' => [
    'auth' => ['api', 'auth:sanctum'],
    'admin' => ['api', 'auth:sanctum'],
],
```

### For Role-Based Access Control

If you have role-based permissions:

```php
'middleware' => [
    'auth' => ['api', 'auth'],
    'admin' => ['api', 'auth', 'role:admin'],
],
```

### For Laravel Permission (Spatie)

If you're using the Spatie Laravel Permission package:

```php
'middleware' => [
    'auth' => ['api', 'auth'],
    'admin' => ['api', 'auth', 'permission:manage-files'],
],
```

### For Custom Authorization

You can create custom middleware for specific authorization logic:

```php
'middleware' => [
    'auth' => ['api', 'auth'],
    'admin' => ['api', 'auth', 'filehub.admin'],
],
```

## Environment Configuration

You can also configure middleware through environment variables by modifying the config:

```php
'middleware' => [
    'auth' => explode(',', env('FILEHUB_AUTH_MIDDLEWARE', 'api,auth')),
    'admin' => explode(',', env('FILEHUB_ADMIN_MIDDLEWARE', 'api,auth')),
],
```

Then in your `.env` file:

```env
FILEHUB_AUTH_MIDDLEWARE=api,auth:sanctum
FILEHUB_ADMIN_MIDDLEWARE=api,auth:sanctum,role:admin
```

## Route Groups

The middleware configurations are used in the following route groups:

### User Routes (`/filehub/api/*`)
- Uses `filehub.middleware.auth` configuration
- Default: `['api', 'auth']`

### Admin Routes (`/filehub/api/admin/*`)
- Uses `filehub.middleware.admin` configuration  
- Default: `['api', 'auth']`

### Upload Routes
- Uses `filehub.middleware.upload` configuration
- Default: `['api', 'auth', 'throttle:uploads']`

## Publishing Configuration

If you haven't published the configuration file yet, run:

```bash
php artisan vendor:publish --provider="Litepie\FileHub\Providers\FileHubServiceProvider" --tag="config"
```

Then modify the middleware settings in `config/filehub.php` according to your needs.

## Examples

### Example 1: Simple Admin Role Check

```php
'middleware' => [
    'auth' => ['api', 'auth'],
    'admin' => ['api', 'auth', 'admin'],
],
```

### Example 2: Multiple Authentication Guards

```php
'middleware' => [
    'auth' => ['api', 'auth:web,api'],
    'admin' => ['api', 'auth:web,api', 'can:admin-panel'],
],
```

### Example 3: Custom Throttling

```php
'middleware' => [
    'auth' => ['api', 'auth'],
    'admin' => ['api', 'auth', 'throttle:admin:100,1'],
    'upload' => ['api', 'auth', 'throttle:uploads:20,1'],
],
```

This configuration system allows you to easily adapt the FileHub package to your application's specific authorization requirements without modifying the package source code.
