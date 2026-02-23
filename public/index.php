<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/**
 * ===========================================
 * DEPLOYMENT CONFIGURATION
 * ===========================================
 * 
 * FOR LOCAL DEVELOPMENT: Use relative paths (current setup)
 * FOR GODADDY CPANEL: Uncomment the production paths below
 * 
 * Replace 'your_username' with your actual cPanel username
 * Example: If your username is 'sliauser', use:
 * /home/sliauser/slia_backend/vendor/autoload.php
 */

// === LOCAL DEVELOPMENT PATHS (ACTIVE) ===
$basePath = __DIR__.'/../';

// === PRODUCTION PATHS (UNCOMMENT FOR DEPLOYMENT) ===
// $basePath = '/home/your_username/slia_backend/';

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = $basePath.'storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require $basePath.'vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once $basePath.'bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
