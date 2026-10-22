<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');

        $query = Department::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $departments = $query->orderBy('code')->paginate(10)->withQueryString();

        return view('master.departments.index', compact('departments', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        Department::create($data);

        return redirect()->route('departments.index')
            ->with('success', "Jurusan {$data['name']} ({$data['code']}) berhasil ditambahkan.");
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code,'.$department->id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $department->update($data);

        return redirect()->route('departments.index')
            ->with('success', "Jurusan {$department->name} berhasil diperbarui.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->classrooms()->exists() || $department->subjects()->exists()) {
            return redirect()->route('departments.index')
                ->with('error', "Jurusan {$department->name} tidak dapat dihapus karena masih memiliki kelas atau mata pelajaran terkait.");
        }

        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', "Jurusan {$department->name} berhasil dihapus.");
    }
}
