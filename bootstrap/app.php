<?php

use App\Http\Middleware\HandshakeMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckPermission;

//use App\Http\Middleware\EnsureTokenIsValid;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        //
        // $middleware->append(EnsureTokenIsValid::class);
        //$middleware->group('api', [

        //]);
        $middleware->alias([
            'jwt' => JwtMiddleware::class,
            'handshake' => HandshakeMiddleware::class,
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
        ]);
    })


    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
