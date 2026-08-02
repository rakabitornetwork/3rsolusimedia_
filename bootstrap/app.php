<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'can.write' => \App\Http\Middleware\EnsureUserCanWrite::class,
        ]);

        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->report(function (\Throwable $e): void {
            $request = request();
            $line = date('c')
                ."\n".get_class($e).': '.$e->getMessage()
                ."\n".$e->getFile().':'.$e->getLine()
                ."\n".($request ? ($request->method().' '.$request->fullUrl()) : 'no-request')
                ."\nuser=".($request?->user()?->id ?? 'guest')
                ."\ninertia=".(($request?->header('X-Inertia')) ? '1' : '0')
                ."\n".$e->getTraceAsString()
                ."\n\n";
            @file_put_contents(storage_path('logs/last-500.txt'), $line, FILE_APPEND);
            @file_put_contents(
                storage_path('logs/service-profiles-debug.log'),
                $line,
                FILE_APPEND
            );
        });
    })->create();
