<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActiveUserController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'sessions'); // 'sessions' or 'users'
        $search = $request->query('search');
        $roleId = $request->query('role_id');
        $statusFilter = $request->query('status');

        $fifteenMinutesAgo = Carbon::now()->subMinutes(15)->timestamp;

        // Statistics
        $totalUsers = User::count();
        $activeAccountCount = User::where('is_active', true)->count();
        $inactiveAccountCount = User::where('is_active', false)->count();

        $onlineUsersCount = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $fifteenMinutesAgo)
            ->distinct('user_id')
            ->count('user_id');

        $activeSessionsCount = DB::table('sessions')
            ->whereNotNull('user_id')
            ->count();

        // 1. Online / Active Sessions Query
        $sessionQuery = DB::table('sessions')
            ->whereNotNull('sessions.user_id')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->select([
                'sessions.id as session_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.id as user_id',
                'users.name as user_name',
                'users.username',
                'users.email',
                'users.is_active',
                'roles.name as role_name',
                'roles.slug as role_slug',
            ]);

        if ($search) {
            $sessionQuery->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('sessions.ip_address', 'like', "%{$search}%");
            });
        }

        if ($roleId) {
            $sessionQuery->where('users.role_id', $roleId);
        }

        $activeSessions = $sessionQuery->orderBy('sessions.last_activity', 'desc')
            ->paginate(15, ['*'], 'sessions_page')
            ->withQueryString();

        // Map human-readable device and last activity
        $activeSessions->getCollection()->transform(function ($item) use ($fifteenMinutesAgo) {
            $item->is_recent_online = $item->last_activity >= $fifteenMinutesAgo;
            $item->last_seen = Carbon::createFromTimestamp($item->last_activity)->diffForHumans();
            $item->device = $this->parseUserAgent($item->user_agent);
            $item->is_current = session()->getId() === $item->session_id;

            return $item;
        });

        // 2. All Users Query
        $userQuery = User::with('role');

        if ($search) {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($roleId) {
            $userQuery->where('role_id', $roleId);
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $userQuery->where('is_active', (bool) $statusFilter);
        }

        $allUsers = $userQuery->orderBy('name')->paginate(20, ['*'], 'users_page')->withQueryString();

        // Check which users have active sessions
        $onlineUserIds = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $fifteenMinutesAgo)
            ->pluck('user_id')
            ->flip()
            ->toArray();

        $roles = Role::orderBy('name')->get();

        return view('superadmin.active_users.index', compact(
            'tab',
            'search',
            'roleId',
            'statusFilter',
            'totalUsers',
            'activeAccountCount',
            'inactiveAccountCount',
            'onlineUsersCount',
            'activeSessionsCount',
            'activeSessions',
            'allUsers',
            'onlineUserIds',
            'roles'
        ));
    }

    /**
     * Terminate an active session (Force Logout).
     */
    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        $session = DB::table('sessions')->where('id', $sessionId)->first();

        if (! $session) {
            return back()->with('warning', 'Sesi tersebut sudah tidak aktif atau telah kedaluwarsa.');
        }

        $targetUser = $session->user_id ? User::find($session->user_id) : null;
        $userName = $targetUser?->name ?? 'Pengguna Anonim';
        $ip = $session->ip_address ?? '-';

        // Check if trying to kill own current session
        if (session()->getId() === $sessionId) {
            return back()->with('error', 'Anda tidak dapat memutus sesi Anda sendiri yang sedang aktif digunakan.');
        }

        DB::table('sessions')->where('id', $sessionId)->delete();

        ActivityLogger::log(
            'force_logout',
            "Memutus paksa sesi login pengguna {$userName} (IP: {$ip}, Sesi: ".substr($sessionId, 0, 8).'...).',
            $targetUser
        );

        return back()->with('success', "Sesi login untuk {$userName} berhasil diputus. Pengguna tersebut harus login kembali.");
    }

    /**
     * Toggle a user's is_active status.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri!');
        }

        $newStatus = ! $user->is_active;
        ActivityLogger::withoutLogging(function () use ($user, $newStatus) {
            $user->is_active = $newStatus;
            $user->save();
        });

        // If deactivated, terminate all active sessions immediately
        if (! $newStatus) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $statusText = $newStatus ? 'diaktifkan kembali' : 'dinonaktifkan (seluruh sesi diputus)';

        ActivityLogger::log(
            'toggle_status',
            "Mengubah status akun pengguna {$user->name} ({$user->username}) menjadi ".($newStatus ? 'Aktif' : 'Nonaktif').'.',
            $user
        );

        return back()->with('success', "Akun {$user->name} berhasil {$statusText}.");
    }

    /**
     * Helper to parse user agent string into friendly browser and OS.
     */
    private function parseUserAgent(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Tidak Diketahui';
        }

        $os = 'Perangkat Lain';
        if (str_contains($userAgent, 'Windows NT 10.0')) {
            $os = 'Windows 10/11';
        } elseif (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X')) {
            $os = 'macOS';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        }

        $browser = 'Browser';
        if (str_contains($userAgent, 'Edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($userAgent, 'Chrome/') && ! str_contains($userAgent, 'Edg/')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome/')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Opera') || str_contains($userAgent, 'OPR/')) {
            $browser = 'Opera';
        }

        return "{$browser} ({$os})";
    }
}
