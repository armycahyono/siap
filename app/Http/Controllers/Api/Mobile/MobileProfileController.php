<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\GtkDecree;
use App\Models\LessonSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileProfileController extends Controller
{
    /**
     * Get GTK profile, teaching assignments, structural roles, and active decrees for SIAP Mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        // 1. Mata Pelajaran yang diampu
        $userSubjects = LessonSchedule::where('teacher_id', $user->id)
            ->with('subject')
            ->get()
            ->pluck('subject.name')
            ->unique()
            ->filter()
            ->values();

        if ($userSubjects->isEmpty() && $user->subject) {
            $userSubjects = collect(explode(',', $user->subject))->map(fn ($s) => trim($s))->filter()->values();
        }

        // 2. Dokumen SK GTK aktif yang menaungi GTK ini
        $activeDecrees = $user->gtkDecrees()
            ->where('status', GtkDecree::STATUS_ACTIVE)
            ->latest('tmt_date')
            ->get()
            ->map(function ($decree) {
                return [
                    'id' => $decree->id,
                    'number' => $decree->decree_number,
                    'title' => $decree->title,
                    'type' => $decree->type_label ?? $decree->decree_type,
                    'tmt_date' => $decree->tmt_date?->format('d M Y'),
                    'end_date' => $decree->end_date ? $decree->end_date->format('d M Y') : 'Seterusnya / Definitif',
                    'signatory_name' => $decree->signatory_name,
                    'signatory_position' => $decree->signatory_position,
                    'has_pdf' => (bool) $decree->pdf_file_path,
                    'download_url' => $decree->pdf_file_path ? route('gtk-decrees.download', $decree) : null,
                ];
            });

        // 3. Penugasan struktural aktif
        $activeStructuralPositions = $user->structuralAssignments()
            ->with('structuralPosition')
            ->where('is_active', true)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'position_name' => $assignment->structuralPosition?->name ?? 'Tugas Tambahan',
                    'start_date' => $assignment->start_date?->format('d M Y'),
                    'end_date' => $assignment->end_date ? $assignment->end_date->format('d M Y') : null,
                ];
            });

        // 4. Kelas perwalian aktif
        $activeHomeroom = $user->homeroomClassrooms()
            ->where('is_active', true)
            ->get()
            ->map(function ($classroom) {
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'grade_level' => $classroom->grade_level ?? null,
                    'academic_year' => $classroom->academic_year ?? '-',
                ];
            });

        $avatarUrl = $user->avatar
            ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/'.$user->avatar))
            : asset('assets/images/avatar-default.png');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'short_name' => $user->short_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'nip' => $user->nip,
                    'nuptk' => $user->nuptk,
                    'nigs' => $user->nigs,
                    'nik' => $user->nik,
                    'position' => $user->position,
                    'gtk_status' => $user->gtk_status ?? 'GTY / Tetap',
                    'education_level' => $user->education_level,
                    'major' => $user->major,
                    'faculty' => $user->faculty,
                    'address' => $user->address,
                    'avatar_url' => $avatarUrl,
                ],
                'subjects' => $userSubjects,
                'structural_positions' => $activeStructuralPositions,
                'homeroom_classrooms' => $activeHomeroom,
                'active_decrees' => $activeDecrees,
            ],
        ]);
    }
}
