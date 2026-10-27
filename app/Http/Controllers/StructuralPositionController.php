<?php

namespace App\Http\Controllers;

use App\Models\StructuralPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StructuralPositionController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('teacher-assignments.index', array_merge(['tab' => 'struktural'], $request->query()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:structural_positions,code'],
            'category' => ['nullable', 'string', 'max:100'],
            'allows_multiple' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['allows_multiple'] = $request->boolean('allows_multiple');
        $data['is_active'] = $request->boolean('is_active');
        $data['order'] = $data['order'] ?? (StructuralPosition::max('order') + 1);

        if ($request->hasFile('signature_image')) {
            $file = $request->file('signature_image');
            $filename = 'sig_pos_'.uniqid().'.'.$file->getClientOriginalExtension();
            $data['signature_image_path'] = $file->storeAs('signatures', $filename, 'local');
        }

        unset($data['signature_image']);

        StructuralPosition::create($data);

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural'])
            ->with('success', "Jabatan Struktural {$data['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, StructuralPosition $structuralPosition): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:structural_positions,code,'.$structuralPosition->id],
            'category' => ['nullable', 'string', 'max:100'],
            'allows_multiple' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_signature' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['allows_multiple'] = $request->boolean('allows_multiple');
        $data['is_active'] = $request->boolean('is_active');

        if ($request->boolean('remove_signature') && $structuralPosition->signature_image_path) {
            if (Storage::disk('local')->exists($structuralPosition->signature_image_path)) {
                Storage::disk('local')->delete($structuralPosition->signature_image_path);
            } elseif (Storage::disk('public')->exists($structuralPosition->signature_image_path)) {
                Storage::disk('public')->delete($structuralPosition->signature_image_path);
            }
            $data['signature_image_path'] = null;
        }

        if ($request->hasFile('signature_image')) {
            $file = $request->file('signature_image');
            $filename = 'sig_pos_'.uniqid().'.'.$file->getClientOriginalExtension();
            $newPath = $file->storeAs('signatures', $filename, 'local');

            if ($newPath) {
                // Hapus file lama hanya setelah file baru berhasil disimpan ke disk privat
                if ($structuralPosition->signature_image_path) {
                    if (Storage::disk('local')->exists($structuralPosition->signature_image_path)) {
                        Storage::disk('local')->delete($structuralPosition->signature_image_path);
                    } elseif (Storage::disk('public')->exists($structuralPosition->signature_image_path)) {
                        Storage::disk('public')->delete($structuralPosition->signature_image_path);
                    }
                }
                $data['signature_image_path'] = $newPath;
            }
        }

        unset($data['signature_image'], $data['remove_signature']);

        $structuralPosition->update($data);

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural'])
            ->with('success', "Jabatan Struktural {$structuralPosition->name} berhasil diperbarui.");
    }

    public function destroy(StructuralPosition $structuralPosition): RedirectResponse
    {
        if ($structuralPosition->assignments()->exists()) {
            return redirect()->route('teacher-assignments.index', ['tab' => 'struktural'])
                ->with('error', "Jabatan Struktural {$structuralPosition->name} tidak dapat dihapus karena masih memiliki riwayat penugasan guru.");
        }

        if ($structuralPosition->signature_image_path) {
            if (Storage::disk('local')->exists($structuralPosition->signature_image_path)) {
                Storage::disk('local')->delete($structuralPosition->signature_image_path);
            } elseif (Storage::disk('public')->exists($structuralPosition->signature_image_path)) {
                Storage::disk('public')->delete($structuralPosition->signature_image_path);
            }
        }

        $name = $structuralPosition->name;
        $structuralPosition->delete();

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural'])
            ->with('success', "Jabatan Struktural {$name} berhasil dihapus.");
    }

    /**
     * Tampilkan tanda tangan digital jabatan struktural dengan otorisasi ketat.
     */
    public function viewSignature(Request $request, StructuralPosition $structuralPosition): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        // Otorisasi: Super Admin, Kepala Sekolah, Pengelola Master Data, atau Pejabat yang ditugaskan pada jabatan ini
        $isAuthorized = $user->isSuperAdmin()
            || $user->isPrincipal()
            || $user->hasPermission('manage_master_data')
            || $structuralPosition->teachers()->where('users.id', $user->id)->exists();

        if (! $isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat tanda tangan digital ini.');
        }

        $path = $structuralPosition->signature_image_path;

        // Path Traversal & Whitelist Protection
        if (! $path || str_contains($path, '..') || str_contains($path, "\0") || ! str_starts_with(str_replace('\\', '/', $path), 'signatures/')) {
            abort(404, 'File tanda tangan tidak valid.');
        }

        // Dual-disk resolution: prioritaskan disk privat 'local', fallback ke disk 'public' untuk data warisan
        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if (! $disk) {
            abort(404, 'File tanda tangan tidak ditemukan di penyimpanan.');
        }

        return Storage::disk($disk)->response($path);
    }
}
