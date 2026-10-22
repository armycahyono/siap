<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $action = $request->query('action');
        $userId = $request->query('user_id');
        $datePreset = $request->query('date_preset', 'all'); // 'today', '7_days', '30_days', 'custom', 'all'
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = UserActivityLog::with('user');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_username', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($action && $action !== 'all') {
            $query->where('action', $action);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Date filtering
        if ($datePreset === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($datePreset === '7_days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($datePreset === '30_days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        } elseif ($datePreset === 'custom' && ($startDate || $endDate)) {
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Summary Counts
        $totalLogsCount = UserActivityLog::count();
        $todayLogsCount = UserActivityLog::whereDate('created_at', Carbon::today())->count();
        $loginLogsCount = UserActivityLog::where('action', 'login')->count();
        $changesCount = UserActivityLog::whereNotIn('action', ['login', 'logout', 'switch_year', 'reset_year'])->count();

        $users = User::orderBy('name')->get();
        $distinctActions = UserActivityLog::distinct()->pluck('action')->filter()->values()->toArray();

        return view('superadmin.activity_logs.index', compact(
            'logs',
            'search',
            'action',
            'userId',
            'datePreset',
            'startDate',
            'endDate',
            'totalLogsCount',
            'todayLogsCount',
            'loginLogsCount',
            'changesCount',
            'users',
            'distinctActions'
        ));
    }

    /**
     * Delete a single activity log entry.
     */
    public function destroy(UserActivityLog $log): RedirectResponse
    {
        $log->delete();

        return back()->with('success', 'Catatan log aktivitas berhasil dihapus.');
    }

    /**
     * Clear activity logs (older than 30/90 days or all).
     */
    public function clear(Request $request): RedirectResponse
    {
        $range = $request->input('range', '30'); // '30', '90', 'all'

        if ($range === 'all') {
            UserActivityLog::truncate();
            $message = 'Semua catatan log aktivitas berhasil dibersihkan.';
        } else {
            $days = (int) $range;
            $cutoff = Carbon::now()->subDays($days);
            $deleted = UserActivityLog::where('created_at', '<', $cutoff)->delete();
            $message = "Catatan log aktivitas yang lebih lama dari {$days} hari ({$deleted} log) berhasil dibersihkan.";
        }

        ActivityLogger::log('delete', $message);

        return back()->with('success', $message);
    }
}
