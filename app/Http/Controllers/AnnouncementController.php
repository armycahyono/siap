<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    /**
     * Tampilkan daftar pengumuman untuk web.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $canPublish = $user->canPublishAnnouncement();

        $search = $request->input('search');
        $tab = $request->input('tab', 'all');

        $query = Announcement::with(['author', 'reads' => function ($q) use ($user) {
            $q->where('user_id', $user->id);
        }])->withCount('reads');

        if ($canPublish && $tab === 'my') {
            $query->where('author_id', $user->id);
        } else {
            $query->active()->forUser($user);
        }

        if ($tab === 'unread') {
            $query->whereDoesntHave('reads', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $announcements = $query->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        // Statistik ringkas
        $unreadCount = $user->unreadAnnouncementsCount();
        $totalActiveCount = Announcement::active()->forUser($user)->count();
        $myAnnouncementsCount = $canPublish ? Announcement::where('author_id', $user->id)->count() : 0;

        // Data pendukung untuk modal tambah / edit
        $availableRoles = Role::orderBy('name')->get();
        $availableDepartments = Department::where('is_active', true)->orderBy('name')->get();

        return view('announcements.index', compact(
            'announcements',
            'canPublish',
            'unreadCount',
            'totalActiveCount',
            'myAnnouncementsCount',
            'availableRoles',
            'availableDepartments',
            'tab',
            'search'
        ));
    }

    /**
     * Simpan pengumuman baru (Khusus KS, Waka, Kajur, Superadmin).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canPublishAnnouncement()) {
            abort(403, 'Hanya Kepala Sekolah, Wakil Kepala Sekolah, dan Ketua Jurusan / Kaprogli yang diizinkan menerbitkan pengumuman.');
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
            'is_pinned' => $request->has('is_pinned'),
            'send_telegram' => $request->has('send_telegram'),
            'is_active' => true,
            'published_at' => now(),
        ]);

        // Otomatis tandai sudah dibaca oleh pembuatnya
        $announcement->markAsRead($user);

        // Siarkan ke Telegram jika dicentang
        $telegramMessage = '';
        if ($announcement->send_telegram) {
            $tgResult = $this->telegramService->sendAnnouncementNotification($announcement);
            if ($tgResult['success']) {
                $telegramMessage = ' & Berhasil disiarkan ke Telegram ('.$tgResult['sent_count'].' penerima).';
            }
        }

        return redirect()->route('announcements.index')
            ->with('success', "Pengumuman '{$announcement->title}' berhasil diterbitkan{$telegramMessage}");
    }

    /**
     * Ambil data detail pengumuman (JSON untuk modal baca / preview).
     */
    public function show(Announcement $announcement, Request $request): JsonResponse
    {
        $user = $request->user();
        $announcement->load('author');

        // Tandai sudah dibaca
        $announcement->markAsRead($user);

        return response()->json([
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'author_name' => $announcement->author ? $announcement->author->name : 'Pimpinan Sekolah',
            'author_role' => $announcement->author ? ($announcement->author->role?->name ?? 'Pimpinan') : 'Pimpinan',
            'target_label' => $announcement->target_label,
            'target_type' => $announcement->target_type,
            'target_roles' => $announcement->target_roles ?? [],
            'target_departments' => $announcement->target_departments ?? [],
            'is_pinned' => $announcement->is_pinned,
            'is_active' => $announcement->is_active,
            'published_at' => $announcement->published_at ? $announcement->published_at->translatedFormat('l, d F Y H:i') : '-',
            'whatsapp_share_url' => $announcement->whatsapp_share_url,
            'whatsapp_share_text' => $announcement->getWhatsAppShareText(),
        ]);
    }

    /**
     * Perbarui pengumuman yang sudah ada.
     */
    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canPublishAnnouncement()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah pengumuman.');
        }

        // Hanya author atau Superadmin/KS yang boleh mengedit
        if (! $user->isSuperAdmin() && ! $user->isPrincipal() && $announcement->author_id !== $user->id) {
            abort(403, 'Anda hanya dapat mengubah pengumuman yang Anda buat sendiri.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'target_type' => 'required|in:all,role,department',
            'target_roles' => 'nullable|array',
            'target_departments' => 'nullable|array',
            'is_pinned' => 'nullable|boolean',
            'send_telegram' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'target_type' => $validated['target_type'],
            'target_roles' => $validated['target_type'] === 'role' ? ($validated['target_roles'] ?? []) : null,
            'target_departments' => $validated['target_type'] === 'department' ? ($validated['target_departments'] ?? []) : null,
            'is_pinned' => $request->has('is_pinned'),
            'send_telegram' => $request->has('send_telegram'),
            'is_active' => $request->has('is_active'),
        ]);

        $telegramMessage = '';
        if ($request->has('resend_telegram') || ($request->has('send_telegram') && ! $announcement->telegram_sent_at)) {
            $tgResult = $this->telegramService->sendAnnouncementNotification($announcement);
            if ($tgResult['success']) {
                $telegramMessage = ' & Berhasil disiarkan ke Telegram ('.$tgResult['sent_count'].' penerima).';
            }
        }

        return redirect()->route('announcements.index')
            ->with('success', "Pengumuman '{$announcement->title}' berhasil diperbarui{$telegramMessage}");
    }

    /**
     * Hapus pengumuman.
     */
    public function destroy(Announcement $announcement, Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canPublishAnnouncement()) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus pengumuman.');
        }

        if (! $user->isSuperAdmin() && ! $user->isPrincipal() && $announcement->author_id !== $user->id) {
            abort(403, 'Anda hanya dapat menghapus pengumuman yang Anda buat sendiri.');
        }

        $title = $announcement->title;
        $announcement->delete();

        return redirect()->route('announcements.index')
            ->with('success', "Pengumuman '{$title}' berhasil dihapus.");
    }

    /**
     * Tandai pengumuman sudah dibaca via AJAX.
     */
    public function markAsRead(Announcement $announcement, Request $request): JsonResponse
    {
        $announcement->markAsRead($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman telah ditandai sudah dibaca.',
            'unread_count' => $request->user()->unreadAnnouncementsCount(),
        ]);
    }

    /**
     * Tandai semua pengumuman aktif sebagai sudah dibaca.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $announcements = Announcement::active()->forUser($user)->get();

        foreach ($announcements as $ann) {
            $ann->markAsRead($user);
        }

        return back()->with('success', 'Semua pengumuman telah ditandai sudah dibaca.');
    }

    /**
     * Siarkan ulang pengumuman ke Telegram.
     */
    public function resendTelegram(Announcement $announcement, Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canPublishAnnouncement()) {
            abort(403, 'Anda tidak memiliki izin menyiarkan ke Telegram.');
        }

        $res = $this->telegramService->sendAnnouncementNotification($announcement);

        if ($res['success']) {
            return back()->with('success', $res['message']);
        }

        return back()->with('error', $res['message']);
    }

    /**
     * Dapatkan rincian status pembaca pengumuman (JSON untuk modal).
     */
    public function readers(Announcement $announcement, Request $request): JsonResponse
    {
        $user = $request->user();

        // Otorisasi: Hanya pembuat pengumuman, Superadmin, KS, atau Waka yang diizinkan melihat rekap pembaca
        if (! $user->isSuperAdmin() && ! $user->isPrincipal() && ! $user->isVicePrincipal() && $announcement->author_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat rincian pembaca pengumuman ini.');
        }

        $targetUsers = $announcement->getTargetUsersQuery()->with('role')->orderBy('name')->get();
        $targetUserIds = $targetUsers->pluck('id')->toArray();

        $reads = $announcement->reads()->with('user.role')->orderByDesc('read_at')->get();
        $readMap = $reads->keyBy('user_id');

        $readUsers = $reads->map(function ($read) use ($targetUserIds) {
            $u = $read->user;

            return [
                'id' => $u?->id,
                'name' => $u?->name ?? 'User Tidak Diketahui',
                'position_or_role' => $u?->position ?: ($u?->role?->name ?? 'GTK'),
                'avatar_url' => $u?->avatar_url ?? null,
                'avatar_initial' => strtoupper(substr($u?->name ?? 'U', 0, 1)),
                'read_at' => $read->read_at ? $read->read_at->translatedFormat('d M Y, H:i') : '-',
                'read_at_human' => $read->read_at ? $read->read_at->diffForHumans() : '-',
                'is_target' => in_array($u?->id, $targetUserIds, true),
            ];
        });

        $unreadUsers = $targetUsers->filter(function ($u) use ($readMap) {
            return ! $readMap->has($u->id);
        })->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'position_or_role' => $u->position ?: ($u->role?->name ?? 'GTK'),
                'avatar_url' => $u->avatar_url ?? null,
                'avatar_initial' => strtoupper(substr($u->name, 0, 1)),
                'has_telegram' => ! empty($u->telegram_chat_id),
            ];
        })->values();

        $totalTarget = $targetUsers->count();
        $totalRead = $reads->count();
        $targetReadCount = $targetUsers->filter(fn ($u) => $readMap->has($u->id))->count();
        $unreadCount = $unreadUsers->count();
        $percentage = $totalTarget > 0 ? (int) round(($targetReadCount / $totalTarget) * 100) : 100;

        return response()->json([
            'success' => true,
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'author_name' => $announcement->author ? $announcement->author->name : 'Pimpinan Sekolah',
                'published_at' => $announcement->published_at ? $announcement->published_at->translatedFormat('l, d F Y H:i') : '-',
                'target_label' => $announcement->target_label,
                'send_telegram' => (bool) $announcement->send_telegram,
            ],
            'summary' => [
                'total_target' => $totalTarget,
                'total_read' => $totalRead,
                'target_read_count' => $targetReadCount,
                'unread_count' => $unreadCount,
                'read_percentage' => $percentage,
            ],
            'read_users' => $readUsers,
            'unread_users' => $unreadUsers,
        ]);
    }

    /**
     * Kirim pesan pengingat ke GTK yang belum membaca pengumuman melalui Telegram.
     */
    public function remindUnread(Announcement $announcement, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->isPrincipal() && ! $user->isVicePrincipal() && $announcement->author_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengirim pengingat.',
            ], 403);
        }

        $targetUsers = $announcement->getTargetUsersQuery()->get();
        $readUserIds = $announcement->reads()->pluck('user_id')->toArray();

        $unreadUsers = $targetUsers->filter(function ($u) use ($readUserIds) {
            return ! in_array($u->id, $readUserIds, true) && ! empty($u->telegram_chat_id);
        });

        if ($unreadUsers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada penerima belum baca yang memiliki akun Telegram aktif.',
            ]);
        }

        $schoolSetting = SchoolSetting::getSetting();
        $schoolName = strtoupper($schoolSetting->school_name ?? 'SMK PGRI KRAS');
        $authorName = $announcement->author ? $announcement->author->name : 'Pimpinan Sekolah';
        $authorRole = $announcement->author ? ($announcement->author->role?->name ?? 'Pimpinan') : 'Pimpinan';

        $sentCount = 0;
        foreach ($unreadUsers as $recipient) {
            $message = "🔔 <b>PENGINGAT PENGUMUMAN SEKOLAH</b>\n"
                ."<b>{$schoolName}</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Yth. Bpk/Ibu <b>{$recipient->name}</b>,\n\n"
                ."Anda memiliki pengumuman penting yang belum dibuka/dibaca:\n"
                ."📌 <b>Judul:</b> {$announcement->title}\n"
                ."👤 <b>Pengirim:</b> {$authorName} ({$authorRole})\n"
                ."🎯 <b>Sasaran:</b> {$announcement->target_label}\n\n"
                ."Mohon untuk membuka dan membaca detail pengumuman melalui <b>SIAP Web</b> atau <b>SIAP Mobile</b>.\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                .'<i>Sistem Informasi Administrasi dan Pendidikan (SIAP)</i>';

            $isSent = $this->telegramService->sendMessage(
                $recipient->telegram_chat_id,
                $message,
                'announcement_reminder',
                $recipient->name,
                'HTML'
            );

            if ($isSent) {
                $sentCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Pengingat berhasil dikirim ke {$sentCount} GTK melalui Telegram.",
            'sent_count' => $sentCount,
        ]);
    }
}
