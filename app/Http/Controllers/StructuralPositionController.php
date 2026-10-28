<?php

namespace App\Http\Controllers;

use App\Models\StructuralPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StructuralPositionController extends Controller
{
    /**
     * Tampilkan halaman pengelolaan Master Penugasan (Struktural & Operasional).
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');
        $scopeType = $request->query('scope_type');

        $query = StructuralPosition::query()
            ->withCount(['assignments', 'activeAssignments']);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($type) && $type !== 'all') {
            $query->where('type', $type);
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('is_active', $status === 'active' || $status === '1');
        }

        if (! empty($scopeType) && $scopeType !== 'all') {
            $query->where('scope_type', $scopeType);
        }

        $totalPositions = StructuralPosition::count();
        $totalStructural = StructuralPosition::where('type', StructuralPosition::TYPE_STRUKTURAL)->count();
        $totalOperational = StructuralPosition::where('type', StructuralPosition::TYPE_OPERASIONAL)->count();
        $totalActive = StructuralPosition::where('is_active', true)->count();
        $totalInactive = StructuralPosition::where('is_active', false)->count();

        $categories = StructuralPosition::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $positions = $query->orderBy('type')
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.structural_positions.index', compact(
            'positions',
            'categories',
            'search',
            'type',
            'status',
            'scopeType',
            'totalPositions',
            'totalStructural',
            'totalOperational',
            'totalActive',
            'totalInactive'
        ));
    }

    /**
     * Simpan jenis tugas baru ke Master Penugasan.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_\-]+$/', 'unique:structural_positions,code'],
            'type' => ['required', 'string', Rule::in([StructuralPosition::TYPE_STRUKTURAL, StructuralPosition::TYPE_OPERASIONAL])],
            'default_tenure_years' => ['required', 'integer', 'min:1', 'max:5'],
            'scope_type' => ['required', 'string', Rule::in(array_keys(StructuralPosition::SCOPES))],
            'category' => ['nullable', 'string', 'max:100'],
            'allows_multiple' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Format kode tugas harus berupa huruf kapital, angka, garis bawah (_), atau strip (-).',
            'code.unique' => 'Kode tugas sudah digunakan oleh jenis tugas lain.',
            'default_tenure_years.min' => 'Masa jabatan minimal 1 tahun.',
            'default_tenure_years.max' => 'Masa jabatan maksimal 5 tahun.',
        ]);

        $data['allows_multiple'] = $request->boolean('allows_multiple');
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $data['order'] = $data['order'] ?? (StructuralPosition::max('order') + 1);

        if ($request->hasFile('signature_image')) {
            $file = $request->file('signature_image');
            $filename = 'sig_pos_'.uniqid().'.'.$file->getClientOriginalExtension();
            $data['signature_image_path'] = $file->storeAs('signatures', $filename, 'local');
        }

        unset($data['signature_image']);

        $position = StructuralPosition::create($data);

        return redirect()->back()
            ->with('success', "Jenis tugas {$position->name} ({$position->code}) berhasil ditambahkan ke Master Penugasan.");
    }

    /**
     * Perbarui jenis tugas yang sudah ada.
     */
    public function update(Request $request, StructuralPosition $structuralPosition): RedirectResponse
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        // Cek perlindungan perubahan kode jika sudah memiliki riwayat penugasan
        if ($structuralPosition->hasHistoricalAssignments()) {
            $requestedCode = $request->input('code');
            if ($requestedCode && $requestedCode !== $structuralPosition->code) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Kode tugas untuk '{$structuralPosition->name}' tidak dapat diubah karena sudah digunakan oleh riwayat penugasan.");
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('structural_positions', 'code')->ignore($structuralPosition->id)],
            'type' => ['required', 'string', Rule::in([StructuralPosition::TYPE_STRUKTURAL, StructuralPosition::TYPE_OPERASIONAL])],
            'default_tenure_years' => ['required', 'integer', 'min:1', 'max:5'],
            'scope_type' => ['required', 'string', Rule::in(array_keys(StructuralPosition::SCOPES))],
            'category' => ['nullable', 'string', 'max:100'],
            'allows_multiple' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_signature' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Format kode tugas harus berupa huruf kapital, angka, garis bawah (_), atau strip (-).',
            'code.unique' => 'Kode tugas sudah digunakan oleh jenis tugas lain.',
            'default_tenure_years.min' => 'Masa jabatan minimal 1 tahun.',
            'default_tenure_years.max' => 'Masa jabatan maksimal 5 tahun.',
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

        return redirect()->back()
            ->with('success', "Jenis tugas {$structuralPosition->name} berhasil diperbarui.");
    }

    /**
     * Beralih status aktif / nonaktif untuk jenis tugas tertentu.
     */
    public function toggle(StructuralPosition $structuralPosition): RedirectResponse
    {
        $structuralPosition->update([
            'is_active' => ! $structuralPosition->is_active,
        ]);

        $statusStr = $structuralPosition->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()
            ->with('success', "Jenis tugas {$structuralPosition->name} berhasil {$statusStr}.");
    }

    /**
     * Hapus jenis tugas permanen jika belum pernah ditugaskan ke personil.
     */
    public function destroy(StructuralPosition $structuralPosition): RedirectResponse
    {
        if ($structuralPosition->hasHistoricalAssignments()) {
            return redirect()->back()
                ->with('error', "Jenis tugas '{$structuralPosition->name}' tidak dapat dihapus permanen karena masih memiliki riwayat penugasan. Anda dapat menonaktifkan status tugas ini.");
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

        return redirect()->back()
            ->with('success', "Jenis tugas {$name} berhasil dihapus dari Master Penugasan.");
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
