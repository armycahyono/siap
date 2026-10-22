<?php

namespace App\Http\Controllers;

use App\Models\Notepad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotepadController extends Controller
{
    /**
     * Tampilkan daftar catatan / notepad milik pengguna.
     */
    public function index(Request $request): View
    {
        $userId = Auth::id();
        $search = $request->query('search');
        $color = $request->query('color');
        $filter = $request->query('filter', 'all'); // all, pinned, reminder

        $query = Notepad::where('user_id', $userId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($color && in_array($color, ['yellow', 'blue', 'green', 'red', 'purple', 'orange'])) {
            $query->where('color', $color);
        }

        if ($filter === 'pinned') {
            $query->where('is_pinned', true);
        } elseif ($filter === 'reminder') {
            $query->whereNotNull('reminder_date');
        }

        // Urutkan: Pinned di atas, lalu reminder_date atau updated_at terbaru
        $notes = $query->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Notepad::where('user_id', $userId)->count(),
            'pinned' => Notepad::where('user_id', $userId)->where('is_pinned', true)->count(),
            'reminder' => Notepad::where('user_id', $userId)->whereNotNull('reminder_date')->count(),
        ];

        return view('notepads.index', compact('notes', 'stats', 'search', 'color', 'filter'));
    }

    /**
     * Simpan catatan baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'in:yellow,blue,green,red,purple,orange'],
            'is_pinned' => ['nullable', 'boolean'],
            'reminder_date' => ['nullable', 'date'],
        ]);

        $validated['user_id'] = Auth::id();
        $validated['color'] = $validated['color'] ?? 'yellow';
        $validated['is_pinned'] = $request->boolean('is_pinned');

        Notepad::create($validated);

        return redirect()->route('notepads.index')->with('success', 'Catatan berhasil disimpan.');
    }

    /**
     * Perbarui catatan yang sudah ada.
     */
    public function update(Request $request, Notepad $notepad): RedirectResponse
    {
        if ($notepad->user_id !== Auth::id()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'in:yellow,blue,green,red,purple,orange'],
            'is_pinned' => ['nullable', 'boolean'],
            'reminder_date' => ['nullable', 'date'],
        ]);

        $validated['color'] = $validated['color'] ?? 'yellow';
        $validated['is_pinned'] = $request->boolean('is_pinned');

        $notepad->update($validated);

        return redirect()->route('notepads.index')->with('success', 'Catatan berhasil diperbarui.');
    }

    /**
     * Toggle status pin catatan.
     */
    public function togglePin(Notepad $notepad): RedirectResponse
    {
        if ($notepad->user_id !== Auth::id()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $notepad->update([
            'is_pinned' => ! $notepad->is_pinned,
        ]);

        $status = $notepad->is_pinned ? 'disematkan di atas' : 'dilepas dari sematan';

        return redirect()->back()->with('success', "Catatan berhasil {$status}.");
    }

    /**
     * Hapus catatan.
     */
    public function destroy(Notepad $notepad): RedirectResponse
    {
        if ($notepad->user_id !== Auth::id()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $notepad->delete();

        return redirect()->route('notepads.index')->with('success', 'Catatan berhasil dihapus.');
    }
}
