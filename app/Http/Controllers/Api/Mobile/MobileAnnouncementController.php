<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileAnnouncementController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    /**
     * Dapatkan daftar pengumuman untuk aplikasi mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $announcements = Announcement::active()
            ->forUser($user)
            ->with(['author', 'reads' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate($request->input('per_page', 20));

        $data = $announcements->getCollection()->map(function ($ann) use ($user) {
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'content' => $ann->content,
                'snippet' => Str::limit(strip_tags($ann->content), 120),
                'author_name' => $ann->author ? $ann->author->name : 'Pimpinan Sekolah',
                'author_role' => $ann->author ? ($ann->author->role?->name ?? 'Pimpinan') : 'Pimpinan',
                'target_label' => $ann->target_label,
                'target_type' => $ann->target_type,
                'is_pinned' => $ann->is_pinned,
                'is_read' => $ann->isReadBy($user),
                'published_at' => $ann->published_at ? $ann->published_at->toIso8601String() : null,
                'formatted_date' => $ann->published_at ? $ann->published_at->translatedFormat('d M Y, H:i') : '-',
                'whatsapp_share_url' => $ann->whatsapp_share_url,
                'whatsapp_share_text' => $ann->getWhatsAppShareText(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'can_publish' => $user->canPublishAnnouncement(),
            'unread_count' => $user->unreadAnnouncementsCount(),
            'pagination' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
            ],
        ]);
    }

    /**
     * Detail pengumuman (sekaligus otomatis menandai dibaca).
     */
    public function show(Announcement $announcement, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $announcement->load('author');
        $announcement->markAsRead($user);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'author_name' => $announcement->author ? $announcement->author->name : 'Pimpinan Sekolah',
                'author_role' => $announcement->author ? ($announcement->author->role?->name ?? 'Pimpinan') : 'Pimpinan',
                'target_label' => $announcement->target_label,
                'target_type' => $announcement->target_type,
                'is_pinned' => $announcement->is_pinned,
                'is_read' => true,
                'published_at' => $announcement->published_at ? $announcement->published_at->toIso8601String() : null,
                'formatted_date' => $announcement->published_at ? $announcement->published_at->translatedFormat('l, d F Y H:i') : '-',
                'whatsapp_share_url' => $announcement->whatsapp_share_url,
                'whatsapp_share_text' => $announcement->getWhatsAppShareText(),
            ],
            'unread_count' => $user->unreadAnnouncementsCount(),
        ]);
    }

    /**
     * Tandai pengumuman sudah dibaca dari mobile.
     */
    public function markAsRead(Announcement $announcement, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $announcement->markAsRead($user);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman telah ditandai sudah dibaca.',
            'unread_count' => $user->unreadAnnouncementsCount(),
        ]);
    }

    /**
     * Buat pengumuman baru dari mobile (hanya pimpinan yang diizinkan).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canPublishAnnouncement()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Kepala Sekolah, Wakil Kepala Sekolah, dan Ketua Jurusan / Kaprogli yang diizinkan menerbitkan pengumuman.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'target_type' => 'required|in:all,role,department',
            'target_roles' => 'nullable|array',
            'target_departments' => 'nullable|array',
            'is_pinned' => 'nullable|boolean',
            'send_telegram' => 'nullable|boolean',
        ]);

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'author_id' => $user->id,
            'target_type' => $validated['target_type'],
            'target_roles' => $validated['target_type'] === 'role' ? ($validated['target_roles'] ?? []) : null,
            'target_departments' => $validated['target_type'] === 'department' ? ($validated['target_departments'] ?? []) : null,
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
            'send_telegram' => (bool) ($validated['send_telegram'] ?? true),
            'is_active' => true,
            'published_at' => now(),
        ]);

        $announcement->markAsRead($user);

        $sentCount = 0;
        if ($announcement->send_telegram) {
            $tgResult = $this->telegramService->sendAnnouncementNotification($announcement);
            $sentCount = $tgResult['sent_count'] ?? 0;
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil diterbitkan.',
            'telegram_sent_count' => $sentCount,
            'data' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
            ],
        ], 201);
    }
}
