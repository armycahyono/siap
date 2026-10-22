<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\StructuralPosition;
use App\Models\Supervision;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $setting = SchoolSetting::getSetting();
        $teachers = User::operationalPersonnel()->where('is_active', true)->orderBy('name')->get();

        $structuralPrincipal = TeacherStructuralAssignment::with('teacher')
            ->whereHas('structuralPosition', fn ($q) => $q->where('code', 'KS')->orWhere('name', 'Kepala Sekolah'))
            ->where('academic_year', $setting->academic_year)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $structuralCurriculumHead = TeacherStructuralAssignment::with('teacher')
            ->whereHas('structuralPosition', fn ($q) => $q->where('code', 'WAKA_KUR')->orWhere('name', 'like', '%Kurikulum%'))
            ->where('academic_year', $setting->academic_year)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        return view('settings.index', compact('setting', 'teachers', 'structuralPrincipal', 'structuralCurriculumHead'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = SchoolSetting::getSetting();

        $data = $request->validate([
            'foundation_name' => ['nullable', 'string'],
            'school_name' => ['required', 'string', 'max:255'],
            'school_npsn' => ['nullable', 'string', 'max:50'],
            'school_address' => ['nullable', 'string'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'school_email' => ['nullable', 'string', 'max:100'],
            'school_website' => ['nullable', 'string', 'max:100'],
            'established_date' => ['nullable', 'date'],
            'establishment_decree' => ['nullable', 'string', 'max:255'],
            'operational_decree' => ['nullable', 'string', 'max:255'],
            'operational_decree_valid_until' => ['nullable', 'date'],
            'accreditation_grade' => ['nullable', 'string', 'max:50'],
            'accreditation_year' => ['nullable', 'string', 'max:20'],
            'accreditation_decree' => ['nullable', 'string', 'max:255'],
            'accreditation_date' => ['nullable', 'date'],
            'accreditation_valid_until' => ['nullable', 'date'],
            'accreditation_institution' => ['nullable', 'string', 'max:100'],
            'vision' => ['nullable', 'string'],
            'mission' => ['nullable', 'string'],
            'goals' => ['nullable', 'string'],
            'principal_id' => ['nullable', 'exists:users,id'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'principal_nip' => ['nullable', 'string', 'max:50'],
            'curriculum_head_id' => ['nullable', 'exists:users,id'],
            'curriculum_head_name' => ['nullable', 'string', 'max:255'],
            'curriculum_head_nip' => ['nullable', 'string', 'max:50'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'in:Ganjil,Genap'],
            'grade_a_min' => ['nullable', 'integer', 'min:1', 'max:100'],
            'grade_b_min' => ['nullable', 'integer', 'min:1', 'max:100'],
            'grade_c_min' => ['nullable', 'integer', 'min:1', 'max:100'],
            'school_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'school_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'school_radius_meters' => ['nullable', 'integer', 'min:10', 'max:10000'],
            'geotag_attendance_enabled' => ['nullable', 'boolean'],
            'geotag_require_selfie' => ['nullable', 'boolean'],
            'geotag_strict_radius' => ['nullable', 'boolean'],
        ]);

        $fillableData = $request->only([
            'foundation_name', 'school_name', 'school_npsn', 'school_address', 'school_phone',
            'school_email', 'school_website', 'established_date', 'establishment_decree',
            'operational_decree', 'operational_decree_valid_until', 'accreditation_grade',
            'accreditation_year', 'accreditation_decree', 'accreditation_date',
            'accreditation_valid_until', 'accreditation_institution', 'vision', 'mission', 'goals',
            'school_latitude', 'school_longitude', 'school_radius_meters',
        ]);

        $fillableData['geotag_attendance_enabled'] = $request->boolean('geotag_attendance_enabled');
        $fillableData['geotag_require_selfie'] = $request->boolean('geotag_require_selfie');
        $fillableData['geotag_strict_radius'] = $request->boolean('geotag_strict_radius');

        // Sync Kepala Sekolah dari Data Guru jika ada input
        if ($request->filled('principal_id')) {
            $principalUser = User::find($data['principal_id']);
            if ($principalUser) {
                $fillableData['principal_id'] = $principalUser->id;
                $fillableData['principal_name'] = $data['principal_name'] ?: $principalUser->name;
                $fillableData['principal_nip'] = Supervision::sanitizeNip($data['principal_nip'] ?: $principalUser->nip);

                if (! empty($fillableData['principal_nip']) && $principalUser->nip !== $fillableData['principal_nip']) {
                    $principalUser->update(['nip' => $fillableData['principal_nip']]);
                }

                $ksRole = Role::where('slug', 'kepala_sekolah')->first();
                if ($ksRole && $principalUser->role_id !== $ksRole->id) {
                    $principalUser->update(['role_id' => $ksRole->id]);
                }
            }
        } elseif ($request->filled('principal_name')) {
            $fillableData['principal_name'] = $data['principal_name'];
            $fillableData['principal_nip'] = Supervision::sanitizeNip($data['principal_nip'] ?? null);
        }

        // Sync Waka Kurikulum dari Data Guru jika ada input
        if ($request->filled('curriculum_head_id')) {
            $wakaUser = User::find($data['curriculum_head_id']);
            if ($wakaUser) {
                $fillableData['curriculum_head_id'] = $wakaUser->id;
                $fillableData['curriculum_head_name'] = $data['curriculum_head_name'] ?: $wakaUser->name;
                $fillableData['curriculum_head_nip'] = Supervision::sanitizeNip($data['curriculum_head_nip'] ?: $wakaUser->nip);

                if (! empty($fillableData['curriculum_head_nip']) && $wakaUser->nip !== $fillableData['curriculum_head_nip']) {
                    $wakaUser->update(['nip' => $fillableData['curriculum_head_nip']]);
                }

                $wakaRole = Role::where('slug', 'waka_kurikulum')->first();
                if ($wakaRole && $wakaUser->role_id !== $wakaRole->id) {
                    $wakaUser->update(['role_id' => $wakaRole->id]);
                }
            }
        } elseif ($request->filled('curriculum_head_name')) {
            $fillableData['curriculum_head_name'] = $data['curriculum_head_name'];
            $fillableData['curriculum_head_nip'] = Supervision::sanitizeNip($data['curriculum_head_nip'] ?? null);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('school', 'public');
            $fillableData['logo_path'] = $path;
        }

        foreach (['academic_year', 'semester', 'grade_a_min', 'grade_b_min', 'grade_c_min'] as $field) {
            if ($request->filled($field)) {
                $fillableData[$field] = $data[$field];
            }
        }

        // Sync Penugasan Struktural untuk Kepala Sekolah jika posisi KS ada
        if (! empty($fillableData['principal_id'])) {
            $ksPosition = StructuralPosition::where('code', 'KS')->orWhere('name', 'Kepala Sekolah')->first();
            if ($ksPosition) {
                TeacherStructuralAssignment::updateOrCreate(
                    [
                        'structural_position_id' => $ksPosition->id,
                        'academic_year' => $fillableData['academic_year'] ?? $setting->academic_year,
                    ],
                    [
                        'teacher_id' => $fillableData['principal_id'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // Sync Penugasan Struktural untuk Waka Kurikulum jika posisi WAKA_KUR ada
        if (! empty($fillableData['curriculum_head_id'])) {
            $wakaPosition = StructuralPosition::where('code', 'WAKA_KUR')->orWhere('name', 'like', '%Kurikulum%')->first();
            if ($wakaPosition) {
                TeacherStructuralAssignment::updateOrCreate(
                    [
                        'structural_position_id' => $wakaPosition->id,
                        'academic_year' => $fillableData['academic_year'] ?? $setting->academic_year,
                    ],
                    [
                        'teacher_id' => $fillableData['curriculum_head_id'],
                        'is_active' => true,
                    ]
                );
            }
        }

        $setting->update($fillableData);

        return back()->with('success', 'Profil sekolah dan identitas resmi berhasil diperbarui.');
    }
}
