<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        if ($permission === 'operate_piket' && $user->canOperatePiket()) {
            return $next($request);
        }

        if ($permission === 'manage_piket_cases' && $user->canManagePiketCases()) {
            return $next($request);
        }

        if ($permission === 'access_piket' && $user->canAccessPiket()) {
            return $next($request);
        }

        if ($permission === 'manage_piket_schedules' && $user->canManagePiketSchedules()) {
            return $next($request);
        }

        if ($permission === 'view_piket_reports' && $user->canViewPiketReports()) {
            return $next($request);
        }

        if ($permission === 'manage_piket_settings' && $user->canManagePiketSettings()) {
            return $next($request);
        }

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403, "Anda tidak memiliki izin '{$permission}' untuk melakukan aksi ini.");
    }
}
