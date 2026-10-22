<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $deptId = $request->query('department_id');

        $query = Subject::with(['department'])
            ->withCount(['assignments']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($deptId) {
            $query->where('department_id', $deptId);
        }

        $subjects = $query->orderBy('order_index')->orderBy('code')->paginate(15)->withQueryString();
        $departments = Department::where('is_active', true)->orderBy('code')->get();
        $categories = ['Umum', 'Kejuruan', 'Muatan Lokal', 'Pilihan'];

        return view('master.subjects.index', compact('subjects', 'departments', 'categories', 'search', 'category', 'deptId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'kkm' => ['nullable', 'integer', 'min:0', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'order_index' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['order_index'] = $data['order_index'] ?? 1;
        $data['kkm'] = $data['kkm'] ?? 75;

        Subject::create($data);

        return redirect()->route('subjects.index')
            ->with('success', "Mata pelajaran {$data['name']} ({$data['code']}) berhasil ditambahkan.");
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:subjects,code,'.$subject->id],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'kkm' => ['nullable', 'integer', 'min:0', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'order_index' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['order_index'] = $data['order_index'] ?? $subject->order_index;
        $data['kkm'] = $data['kkm'] ?? ($subject->kkm ?? 75);

        $subject->update($data);

        return redirect()->route('subjects.index')
            ->with('success', "Mata pelajaran {$subject->name} berhasil diperbarui.");
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        if ($subject->assignments()->exists()) {
            return redirect()->route('subjects.index')
                ->with('error', "Mata pelajaran {$subject->name} tidak dapat dihapus karena masih ditugaskan pada guru.");
        }

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', "Mata pelajaran {$subject->name} berhasil dihapus.");
    }
}
