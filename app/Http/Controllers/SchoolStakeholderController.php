<?php

namespace App\Http\Controllers;

use App\Models\SchoolStakeholder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SchoolStakeholderController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $category = $request->query('category', 'all');

        $query = SchoolStakeholder::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $stakeholders = $query->orderBy('order_index')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = SchoolStakeholder::categoryDefinitions();
        $endorsementOptions = SchoolStakeholder::endorsementTitleOptions();

        // Count per category for tab pills
        $counts = [
            'all' => SchoolStakeholder::count(),
            'yayasan' => SchoolStakeholder::where('category', 'yayasan')->count(),
            'komite' => SchoolStakeholder::where('category', 'komite')->count(),
            'pengawas' => SchoolStakeholder::where('category', 'pengawas')->count(),
            'dinas' => SchoolStakeholder::where('category', 'dinas')->count(),
        ];

        return view('master.stakeholders.index', compact(
            'stakeholders',
            'categories',
            'endorsementOptions',
            'counts',
            'category',
            'search'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:yayasan,komite,pengawas,dinas'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30'],
            'nik' => ['nullable', 'string', 'max:30'],
            'rank_group' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'is_signatory' => ['nullable', 'boolean'],
            'endorsement_title' => ['nullable', 'string', 'max:50'],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['is_signatory'] = $request->boolean('is_signatory', true);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['order_index'] = $data['order_index'] ?? ((SchoolStakeholder::where('category', $data['category'])->max('order_index') ?? 0) + 1);

        if ($request->hasFile('signature_image')) {
            $file = $request->file('signature_image');
            $filename = 'sig_'.uniqid().'.'.$file->getClientOriginalExtension();
            $data['signature_image_path'] = $file->storeAs('signatures', $filename, 'public');
        }

        unset($data['signature_image']);

        SchoolStakeholder::create($data);

        return redirect()->route('stakeholders.index', ['category' => $data['category']])
            ->with('success', "Data {$data['name']} ({$data['position']}) berhasil ditambahkan.");
    }

    public function update(Request $request, SchoolStakeholder $stakeholder): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:yayasan,komite,pengawas,dinas'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30'],
            'nik' => ['nullable', 'string', 'max:30'],
            'rank_group' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'is_signatory' => ['nullable', 'boolean'],
            'endorsement_title' => ['nullable', 'string', 'max:50'],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['is_signatory'] = $request->boolean('is_signatory');
        $data['is_active'] = $request->boolean('is_active');
        $data['order_index'] = $data['order_index'] ?? $stakeholder->order_index;

        if ($request->boolean('remove_signature') && $stakeholder->signature_image_path) {
            Storage::disk('public')->delete($stakeholder->signature_image_path);
            $data['signature_image_path'] = null;
        }

        if ($request->hasFile('signature_image')) {
            if ($stakeholder->signature_image_path) {
                Storage::disk('public')->delete($stakeholder->signature_image_path);
            }
            $file = $request->file('signature_image');
            $filename = 'sig_'.uniqid().'.'.$file->getClientOriginalExtension();
            $data['signature_image_path'] = $file->storeAs('signatures', $filename, 'public');
        }

        unset($data['signature_image'], $data['remove_signature']);

        $stakeholder->update($data);

        return redirect()->route('stakeholders.index', ['category' => $data['category']])
            ->with('success', "Data {$stakeholder->name} berhasil diperbarui.");
    }

    public function destroy(SchoolStakeholder $stakeholder): RedirectResponse
    {
        $name = $stakeholder->name;

        if ($stakeholder->signature_image_path) {
            Storage::disk('public')->delete($stakeholder->signature_image_path);
        }

        $stakeholder->delete();

        return redirect()->route('stakeholders.index')
            ->with('success', "Data {$name} berhasil dihapus.");
    }
}
