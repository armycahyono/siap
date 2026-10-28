<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkStudentAssessment;
use App\Models\BkStudentCareerProfile;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BkCareerProfileController extends Controller
{
    /**
     * Simpan atau perbarui profil minat, bakat, dan rencana karier siswa untuk tahun ajaran tertentu.
     */
    public function storeOrUpdate(Student $student, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'post_graduation_plan' => ['nullable', 'in:bekerja,melanjutkan,wirausaha,belum_memutuskan'],
            'hobbies' => ['nullable', 'string'],
            'talents' => ['nullable', 'string'],
            'skills_to_develop' => ['nullable', 'string'],
            'favorite_subjects' => ['nullable', 'string'],
            'career_aspirations' => ['nullable', 'string', 'max:255'],
            'target_industry' => ['nullable', 'string', 'max:255'],
            'target_job_position' => ['nullable', 'string', 'max:255'],
            'target_university' => ['nullable', 'string', 'max:255'],
            'target_study_program' => ['nullable', 'string', 'max:255'],
            'entrepreneurship_type' => ['nullable', 'string', 'max:255'],
            'business_plan_summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $user->canOperateBk($validated['academic_year'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola data bimbingan konseling siswa.');
        }

        $academicYearId = AcademicYear::where('name', $validated['academic_year'])->value('id');

        DB::transaction(function () use ($student, $validated, $academicYearId, $user) {
            $profile = BkStudentCareerProfile::where('student_id', $student->id)
                ->where('academic_year', $validated['academic_year'])
                ->first();

            $data = [
                'academic_year_id' => $academicYearId,
                'hobbies' => $validated['hobbies'] ?? null,
                'talents' => $validated['talents'] ?? null,
                'skills_to_develop' => $validated['skills_to_develop'] ?? null,
                'favorite_subjects' => $validated['favorite_subjects'] ?? null,
                'career_aspirations' => $validated['career_aspirations'] ?? null,
                'post_graduation_plan' => $validated['post_graduation_plan'] ?? 'belum_memutuskan',
                'target_industry' => $validated['target_industry'] ?? null,
                'target_job_position' => $validated['target_job_position'] ?? null,
                'target_university' => $validated['target_university'] ?? null,
                'target_study_program' => $validated['target_study_program'] ?? null,
                'entrepreneurship_type' => $validated['entrepreneurship_type'] ?? null,
                'business_plan_summary' => $validated['business_plan_summary'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $user->id,
            ];

            if (! $profile) {
                $data['student_id'] = $student->id;
                $data['academic_year'] = $validated['academic_year'];
                $data['recorded_by'] = $user->id;
                BkStudentCareerProfile::create($data);
            } else {
                $profile->update($data);
            }
        });

        return redirect()
            ->to(route('bk.students.history', ['student' => $student, 'academic_year' => $validated['academic_year']]).'#tab-minat')
            ->with('success', 'Profil minat, bakat, dan rencana karier siswa berhasil diperbarui.');
    }

    /**
     * Tambahkan catatan hasil asesmen minat dan bakat siswa.
     */
    public function storeAssessment(Student $student, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'assessment_date' => ['required', 'date'],
            'assessment_name' => ['required', 'string', 'max:255'],
            'assessor_name' => ['nullable', 'string', 'max:255'],
            'result_summary' => ['required', 'string'],
            'recommended_career_paths' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $user->canOperateBk($validated['academic_year'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola data bimbingan konseling siswa.');
        }

        $academicYearId = AcademicYear::where('name', $validated['academic_year'])->value('id');

        BkStudentAssessment::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'academic_year' => $validated['academic_year'],
            'assessment_date' => $validated['assessment_date'],
            'assessment_name' => $validated['assessment_name'],
            'assessor_name' => $validated['assessor_name'] ?? null,
            'result_summary' => $validated['result_summary'],
            'recommended_career_paths' => $validated['recommended_career_paths'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $user->id,
        ]);

        return redirect()
            ->to(route('bk.students.history', ['student' => $student, 'academic_year' => $validated['academic_year']]).'#tab-minat')
            ->with('success', 'Hasil asesmen minat dan bakat siswa berhasil ditambahkan.');
    }

    /**
     * Hapus rekaman asesmen minat dan bakat.
     */
    public function destroyAssessment(Student $student, BkStudentAssessment $assessment): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($assessment->student_id !== $student->id) {
            abort(404, 'Data asesmen tidak ditemukan untuk siswa ini.');
        }

        if (! $user->canOperateBk($assessment->academic_year)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus riwayat asesmen siswa pada tahun ajaran ini.');
        }

        $academicYear = $assessment->academic_year;
        $assessment->delete();

        return redirect()
            ->to(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]).'#tab-minat')
            ->with('success', 'Riwayat asesmen siswa berhasil dihapus.');
    }
}
