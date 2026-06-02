<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Cookie\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureLoggedInFromIMS;
use App\Http\Middleware\EnsureLoggedInAndDBRoleSelected;
use App\Http\Middleware\EnsureOfficialWebClientOrLoggedInFromIMS;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            TrimStrings::class,
            EncryptCookies::class,
            //HandleCors::class,
        ]);
        $middleware->alias([
            'ims.logged_in' => EnsureLoggedInFromIMS::class,
            'ims.logged_in_and_role_selected' => EnsureLoggedInAndDBRoleSelected::class,            
            'official_web_client_or_logged_in' => EnsureOfficialWebClientOrLoggedInFromIMS::class,      
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
