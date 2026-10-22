<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
        $semester = $request->query('semester', $setting->semester);

        $teachingRoles = ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah'];

        $query = User::whereHas('role', fn ($q) => $q->whereIn('slug', $teachingRoles))
            ->with([
                'supervisionsAsTeacher' => fn ($q) => $q->where('academic_year', $academicYear)->latest(),
                'structuralAssignments' => fn ($q) => $q->where('academic_year', $academicYear)->where('is_active', true)->with('structuralPosition'),
                'homeroomClassrooms' => fn ($q) => $q->where('academic_year', $academicYear),
                'subjectAssignments' => fn ($q) => $q->where('academic_year', $academicYear)->with('subject'),
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('nigs', 'like', "%{$search}%")
                    ->orWhere('nigs_fix', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nuptk', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('birth_info', 'like', "%{$search}%")
                    ->orWhere('education_level', 'like', "%{$search}%")
                    ->orWhere('major', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhereHas('subjectAssignments.subject', fn ($subQ) => $subQ->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        $teachers = $query->orderBy('name')->paginate(15)->withQueryString();

        $allTeachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah']))
            ->orderBy('name')
            ->get();

        return view('teachers.index', compact('teachers', 'search', 'allTeachers', 'academicYear', 'semester'));
    }

    public function create(): View
    {
        return view('teachers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'nik' => ['nullable', 'string', 'max:30'],
            'nigs_fix' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'string', 'max:50'],
            'nuptk' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'birth_info' => ['nullable', 'string', 'max:255'],
            'education_level' => ['nullable', 'string', 'max:100'],
            'major' => ['nullable', 'string', 'max:255'],
        ]);

        $guruRole = Role::where('slug', 'guru')->firstOrFail();

        $data['password'] = Hash::make($data['password']);
        $data['role_id'] = $guruRole->id;
        $data['is_active'] = true;

        User::create($data);

        return redirect()->route('teachers.index')->with('success', 'Data guru berhasil ditambahkan.');
    }

    public function edit(User $teacher): View
    {
        return view('teachers.edit', compact('teacher'));
    }

    public function update(Request $request, User $teacher): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username,'.$teacher->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$teacher->id],
            'password' => ['nullable', 'string', 'min:6'],
            'nik' => ['nullable', 'string', 'max:30'],
            'nigs_fix' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'string', 'max:50'],
            'nuptk' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'birth_info' => ['nullable', 'string', 'max:255'],
            'education_level' => ['nullable', 'string', 'max:100'],
            'major' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $request->has('is_active');

        $teacher->update($data);

        return redirect()->route('teachers.index')->with('success', 'Data guru berhasil diperbarui.');
    }

    public function destroy(User $teacher): RedirectResponse
    {
        // Don't delete if teacher has supervision history to preserve official records
        if ($teacher->supervisionsAsTeacher()->exists() || $teacher->supervisionsAsSupervisor()->exists()) {
            return redirect()->route('teachers.index')
                ->with('error', "Guru {$teacher->name} tidak dapat dihapus karena memiliki riwayat supervisi resmi. Silakan nonaktifkan akun melalui opsi status di menu Edit.");
        }

        $teacher->delete();

        return redirect()->route('teachers.index')->with('success', 'Data guru berhasil dihapus.');
    }
}
