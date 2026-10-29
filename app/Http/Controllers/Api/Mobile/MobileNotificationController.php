<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileNotificationController extends Controller
{
    /**
     * Get list of active notifications, role-based tasks, and reminders for authenticated GTK.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        try {
            $notifications = NotificationService::getUserNotifications($user);
        } catch (\Throwable $e) {
            $notifications = collect();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications->values(),
                'unread_count' => $notifications->count(),
            ],
        ]);
    }
}
