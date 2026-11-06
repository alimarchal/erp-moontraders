<?php

use App\Http\Middleware\CheckUserStatus;
use App\Http\Middleware\SetDatabaseAuditContext;
use App\Support\PhpLimits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add audit context middleware to web group (applies to all web requests)
        $middleware->web(append: [
            SetDatabaseAuditContext::class,
        ]);

        $middleware->web(append: [
            CheckUserStatus::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Too much was attached at once: say so plainly instead of showing a bare 413 page.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $sent = (int) $request->server('CONTENT_LENGTH');
            $limit = PhpLimits::postMaxBytes();

            return back()->with('error', 'The files are too large to save together'
                .($sent > 0 ? ' ('.round($sent / 1048576, 1).' MB sent' : '').($limit > 0 ? ($sent > 0 ? ', ' : ' (').'this server accepts up to '.round($limit / 1048576).' MB in one save' : '').($sent > 0 || $limit > 0 ? ')' : '')
                .'. Attach fewer or smaller files, or ask your administrator to raise post_max_size.');
        });
    })->create();
