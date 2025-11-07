<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetDatabaseAuditContext
{
    /**
     * Handle an incoming request and set database session variables for audit trail.
     *
     * This middleware automatically captures:
     * - Current authenticated user ID
     * - Client IP address
     * - User agent (browser/client info)
     * 
     * These values are then available to database triggers for automatic audit logging.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only set context if user is authenticated
        if (Auth::check()) {
            $userId = Auth::id();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent() ?? 'Unknown';

            // Set session variables based on database driver
            $driver = DB::connection()->getDriverName();

            try {
                if ($driver === 'pgsql') {
                    // PostgreSQL: Use set_config for session variables
                    DB::statement("SELECT set_config('app.current_user_id', ?, false)", [$userId]);
                    DB::statement("SELECT set_config('app.ip_address', ?, false)", [$ipAddress]);
                    DB::statement("SELECT set_config('app.user_agent', ?, false)", [
                        str_replace("'", "''", $userAgent) // Escape single quotes
                    ]);
                } elseif (in_array($driver, ['mysql', 'mariadb'])) {
                    // MySQL: Use user-defined variables
                    DB::statement("SET @current_user_id = ?", [$userId]);
                    DB::statement("SET @ip_address = ?", [$ipAddress]);
                    DB::statement("SET @user_agent = ?", [$userAgent]);
                }
            } catch (\Exception $e) {
                // Log error but don't break the application
                \Log::warning('Failed to set database audit context: ' . $e->getMessage());
            }
        }

        $response = $next($request);

        // Optional: Clear context after request (PostgreSQL only needs this for persistent connections)
        if (Auth::check() && DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement("SELECT set_config('app.current_user_id', NULL, false)");
                DB::statement("SELECT set_config('app.ip_address', NULL, false)");
                DB::statement("SELECT set_config('app.user_agent', NULL, false)");
            } catch (\Exception $e) {
                // Silently fail - not critical
            }
        }

        return $response;
    }
}
