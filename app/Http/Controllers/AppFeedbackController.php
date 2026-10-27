<?php

namespace App\Http\Controllers;

use App\Models\AppFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppFeedbackController extends Controller
{
    /**
     * Display a listing of feedback and improvement notes.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = $request->query('search');
        $tab = $request->query('tab', 'all');
        $category = $request->query('category');
        $status = $request->query('status');

        $query = AppFeedback::with(['user.role', 'responder']);

        if ($tab === 'mine') {
            $query->where('user_id', $user->id);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $feedbacks = $query->latest()->paginate(10)->withQueryString();

        $stats = [
            'total' => AppFeedback::count(),
            'pending' => AppFeedback::where('status', 'menunggu')->count(),
            'in_progress' => AppFeedback::where('status', 'diproses')->count(),
            'completed' => AppFeedback::where('status', 'selesai')->count(),
            'mine' => AppFeedback::where('user_id', $user->id)->count(),
        ];

        return view('feedback.index', compact('feedbacks', 'stats', 'search', 'tab', 'category', 'status'));
    }

    /**
     * Store a newly created feedback note.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:saran_fitur,perbaikan_tampilan,laporan_bug,konten_data,lainnya'],
            'priority' => ['required', 'string', 'in:rendah,sedang,tinggi'],
            'description' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
        ]);

        $attachmentPath = null;
        $attachmentOriginalName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentOriginalName = $file->getClientOriginalName();
            $fileName = 'fb_'.uniqid().'.'.$file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('feedback_attachments', $fileName, 'local');
        }

        AppFeedback::create([
            'user_id' => Auth::id(),
            'category' => $request->input('category'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'priority' => $request->input('priority', 'sedang'),
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentOriginalName,
            'status' => 'menunggu',
        ]);

        return redirect()->route('feedbacks.index')
            ->with('success', 'Catatan dan masukan perbaikan Anda berhasil dikirim. Terima kasih atas kontribusi Anda dalam pengembangan aplikasi!');
    }

    /**
     * Update admin feedback response and status (Admin / Waka Kurikulum only).
     */
    public function updateResponse(Request $request, AppFeedback $feedback): RedirectResponse
    {
        $user = Auth::user();

        if (! ($user->hasRole('super_admin') || $user->isWakaKurikulum())) {
            abort(403, 'Hanya administrator atau Waka Kurikulum yang dapat memberikan tanggapan dan memperbarui status.');
        }

        $request->validate([
            'status' => ['required', 'string', 'in:menunggu,diproses,selesai,ditolak'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $feedback->update([
            'status' => $request->input('status'),
            'admin_notes' => $request->input('admin_notes'),
            'responded_by_id' => $user->id,
            'responded_at' => now(),
        ]);

        return redirect()->route('feedbacks.index')
            ->with('success', 'Status dan tanggapan atas masukan perbaikan berhasil diperbarui.');
    }

    /**
     * Remove the specified feedback note.
     */
    public function destroy(AppFeedback $feedback): RedirectResponse
    {
        $user = Auth::user();

        $canDelete = ($feedback->user_id === $user->id && $feedback->status === 'menunggu')
            || $user->hasRole('super_admin')
            || $user->isWakaKurikulum();

        if (! $canDelete) {
            abort(403, 'Anda tidak memiliki hak untuk menghapus catatan perbaikan ini.');
        }

        if ($feedback->attachment_path) {
            if (Storage::disk('local')->exists($feedback->attachment_path)) {
                Storage::disk('local')->delete($feedback->attachment_path);
            } elseif (Storage::disk('public')->exists($feedback->attachment_path)) {
                Storage::disk('public')->delete($feedback->attachment_path);
            }
        }

        $feedback->delete();

        return redirect()->route('feedbacks.index')
            ->with('success', 'Catatan perbaikan berhasil dihapus.');
    }

    /**
     * Download or view private feedback attachment.
     */
    public function downloadAttachment(AppFeedback $feedback)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $isAuthorized = ($feedback->user_id === $user->id)
            || $user->hasRole('super_admin')
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum())
            || (method_exists($user, 'isPrincipal') && $user->isPrincipal());

        if (! $isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh lampiran masukan ini.');
        }

        $path = $feedback->attachment_path;
        if (! $path || str_contains($path, '..')) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if (! $disk) {
            abort(404, 'File lampiran tidak ditemukan di penyimpanan.');
        }

        return Storage::disk($disk)->response($path, $feedback->attachment_original_name);
    }
}
