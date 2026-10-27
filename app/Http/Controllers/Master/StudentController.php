<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\StudentMutation;
use App\Services\AcademicYearContext;
use App\Services\ActivityLogger;
use App\Services\DapodikStudentImportService;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(
        protected StudentImportService $importService,
        protected DapodikStudentImportService $dapodikImportService
    ) {}

    /**
     * Parse tahun pelajaran (e.g. "2025/2026") menjadi tahun awal dan tahun akhir integer.
     */
    protected function parseAcademicYear(string $academicYear): array
    {
        $startYear = null;
        $endYear = null;
        if (preg_match('/^(\d{4})\/(\d{4})$/', $academicYear, $matches)) {
            $startYear = (int) $matches[1];
            $endYear = (int) $matches[2];
        }

        return [$startYear, $endYear];
    }

    /**
     * Scope query untuk siswa aktif berdasarkan konteks tahun pelajaran terpilih.
     */
    protected function scopeActiveForYear($query, string $academicYear, ?AcademicYear $activeAy, ?int $startYear)
    {
        return $query->where(function ($q) use ($academicYear, $activeAy, $startYear) {
            if ($activeAy) {
                $q->whereHas('studentClassrooms', function ($sq) use ($activeAy) {
                    $sq->where('academic_year_id', $activeAy->id)
                        ->where('status', 'aktif');
                });
            }
            $q->orWhereHas('classroom', function ($cq) use ($academicYear) {
                $cq->where('academic_year', $academicYear);
            });
            // Fallback jika belum dimasukkan ke student_classrooms tapi merupakan angkatan aktif di tahun tersebut
            $q->orWhere(function ($fb) use ($startYear, $academicYear) {
                $fb->whereNull('students.classroom_id')
                    ->doesntHave('studentClassrooms');
                if ($startYear) {
                    $fb->whereIn('students.entry_year', [$startYear, $startYear - 1, $startYear - 2])
                        ->where(function ($sq) use ($academicYear) {
                            $sq->where('students.status', 'aktif')
                                ->orWhere(function ($lq) use ($academicYear) {
                                    $lq->where('students.status', 'lulus')
                                        ->where('students.graduation_year', '>=', $academicYear);
                                });
                        });
                }
            });
        });
    }

    /**
     * Scope query untuk alumni (siswa lulus) berdasarkan konteks tahun pelajaran terpilih.
     * Siswa angkatan <= (startYear - 3) atau yang lulus pada/sebelum tahun pelajaran tersebut.
     */
    protected function scopeAlumniForYear($query, string $academicYear, ?AcademicYear $activeAy, ?int $startYear, ?string $graduationYear = null, bool $isCurrentOrMasterYear = false)
    {
        $query->where(function ($aq) use ($startYear) {
            $aq->where('students.status', 'lulus')
                ->orWhere(function ($sub) use ($startYear) {
                    if ($startYear) {
                        $sub->whereNotNull('students.entry_year')
                            ->where('students.entry_year', '<=', $startYear - 3)
                            ->whereNotIn('students.status', ['mutasi', 'keluar']);
                    }
                });
        });

        // Siswa yang berstatus aktif di rombel TP yang dipilih bukanlah alumni untuk periode ini
        if ($activeAy) {
            $query->whereDoesntHave('studentClassrooms', function ($sq) use ($activeAy) {
                $sq->where('academic_year_id', $activeAy->id)
                    ->where('status', 'aktif');
            });
        }

        // Filter batasan kelulusan: kelulusan sebelum atau pada TP tersebut
        if ($startYear) {
            $maxGradStartYear = $startYear - 1;
            $query->where(function ($gq) use ($startYear, $maxGradStartYear, $isCurrentOrMasterYear) {
                $gq->where(function ($sub) use ($startYear) {
                    $sub->whereNotNull('students.entry_year')
                        ->where('students.entry_year', '<=', $startYear - 3);
                })->orWhere(function ($sub) use ($maxGradStartYear) {
                    $sub->whereNotNull('students.graduation_year')
                        ->where(function ($yq) use ($maxGradStartYear) {
                            $yq->where('students.graduation_year', '<=', "{$maxGradStartYear}/".($maxGradStartYear + 1))
                                ->orWhere('students.graduation_year', '<=', (string) $maxGradStartYear);
                        });
                });

                if ($isCurrentOrMasterYear) {
                    $gq->orWhere(function ($fb) {
                        $fb->where('students.status', 'lulus')
                            ->whereNull('students.entry_year')
                            ->whereNull('students.graduation_year');
                    });
                }
            });
        }

        if ($graduationYear) {
            $query->where('students.graduation_year', $graduationYear);
        }

        return $query;
    }

    /**
     * Scope query untuk siswa mutasi/keluar yang terjadi dalam rentang tahun pelajaran terpilih.
     */
    protected function scopeMutasiForYear($query, string $academicYear, ?AcademicYear $activeAy, ?int $startYear, ?int $endYear, ?string $status = null, bool $isCurrentOrMasterYear = false)
    {
        $startDate = $startYear ? "{$startYear}-07-01" : null;
        $endDate = $endYear ? "{$endYear}-06-30" : null;

        $query->where(function ($mq) use ($startDate, $endDate, $academicYear, $activeAy, $isCurrentOrMasterYear) {
            if ($startDate && $endDate) {
                $mq->whereHas('mutations', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('mutation_date', [$startDate, $endDate]);
                });
            }
            if ($activeAy) {
                $mq->orWhereHas('studentClassrooms', function ($sq) use ($activeAy) {
                    $sq->where('academic_year_id', $activeAy->id)
                        ->whereIn('status', ['mutasi', 'keluar']);
                });
            }
            $mq->orWhere(function ($sub) use ($academicYear) {
                $sub->whereHas('classroom', function ($cq) use ($academicYear) {
                    $cq->where('academic_year', $academicYear);
                })->whereIn('students.status', ['mutasi', 'keluar']);
            });

            // Fallback untuk data mutasi tanpa relasi mutasi resmi saat melihat tahun aktif berjalan
            if ($isCurrentOrMasterYear) {
                $mq->orWhere(function ($fb) {
                    $fb->whereIn('students.status', ['mutasi', 'keluar'])
                        ->doesntHave('mutations')
                        ->doesntHave('studentClassrooms');
                });
            }
        });

        if ($status && in_array($status, ['mutasi', 'keluar'])) {
            $query->where('students.status', $status);
        }

        return $query;
    }

    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? SchoolSetting::getMasterYear()));

        $tab = $request->query('tab', 'aktif');
        $classroomId = $request->query('classroom_id');
        $gender = $request->query('gender');
        $status = $request->query('status');
        $graduationYear = $request->query('graduation_year');
        $search = $request->query('search');
        $sort = $request->query('sort', 'name');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $activeAy = AcademicYear::where('name', $academicYear)->first();
        [$startYear, $endYear] = $this->parseAcademicYear($academicYear);
        $masterYear = SchoolSetting::getMasterYear();
        $isCurrentOrMasterYear = ($academicYear === $masterYear || (bool) $activeAy?->is_active);

        $query = Student::with([
            'classroom.homeroomTeacher',
            'latestMutation.recordedBy',
            'studentClassrooms' => function ($q) use ($activeAy) {
                if ($activeAy) {
                    $q->where('academic_year_id', $activeAy->id);
                }
            },
        ]);

        if ($sort === 'classroom') {
            $query->leftJoin('classrooms', 'students.classroom_id', '=', 'classrooms.id')
                ->orderBy('classrooms.name', $direction)
                ->select('students.*');
        } elseif ($sort === 'homeroom') {
            $query->leftJoin('classrooms', 'students.classroom_id', '=', 'classrooms.id')
                ->leftJoin('users', 'classrooms.homeroom_teacher_id', '=', 'users.id')
                ->orderBy('users.name', $direction)
                ->select('students.*');
        } elseif ($sort === 'latest') {
            $query->latest();
        } else {
            $query->orderBy('students.name', $direction);
        }

        if ($tab === 'alumni') {
            $this->scopeAlumniForYear($query, $academicYear, $activeAy, $startYear, $graduationYear, $isCurrentOrMasterYear);
        } elseif ($tab === 'mutasi') {
            $this->scopeMutasiForYear($query, $academicYear, $activeAy, $startYear, $endYear, $status, $isCurrentOrMasterYear);
        } else {
            // Default: Siswa Aktif terdaftar pada Tahun Pelajaran yang dipilih
            if ($classroomId) {
                $query->where(function ($cq) use ($classroomId, $activeAy) {
                    if ($activeAy) {
                        $cq->whereHas('studentClassrooms', function ($sq) use ($classroomId, $activeAy) {
                            $sq->where('academic_year_id', $activeAy->id)
                                ->where('classroom_id', $classroomId)
                                ->where('status', 'aktif');
                        });
                    }
                    $cq->orWhere('students.classroom_id', $classroomId);
                });
            } else {
                $this->scopeActiveForYear($query, $academicYear, $activeAy, $startYear);
            }
        }

        if ($gender && in_array($gender, ['L', 'P'])) {
            $query->where('students.gender', $gender);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('students.name', 'like', "%{$search}%")
                    ->orWhere('students.nisn', 'like', "%{$search}%")
                    ->orWhere('students.nis', 'like', "%{$search}%")
                    ->orWhere('students.nik', 'like', "%{$search}%")
                    ->orWhere('students.father_name', 'like', "%{$search}%")
                    ->orWhere('students.mother_name', 'like', "%{$search}%")
                    ->orWhere('students.last_classroom_name', 'like', "%{$search}%")
                    ->orWhere('students.graduation_year', 'like', "%{$search}%");
            });
        }

        $students = $query->paginate(20)->withQueryString();

        $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get();

        // Statistik Tab Pills spesifik per konteks Tahun Pelajaran
        $activeQuery = Student::query();
        $this->scopeActiveForYear($activeQuery, $academicYear, $activeAy, $startYear);
        $activeCount = (clone $activeQuery)->count();

        $alumniBaseQuery = Student::query();
        $this->scopeAlumniForYear($alumniBaseQuery, $academicYear, $activeAy, $startYear, null, $isCurrentOrMasterYear);
        $alumniCount = (clone $alumniBaseQuery)->count();

        $mutasiBaseQuery = Student::query();
        $this->scopeMutasiForYear($mutasiBaseQuery, $academicYear, $activeAy, $startYear, $endYear, null, $isCurrentOrMasterYear);
        $mutatedCount = (clone $mutasiBaseQuery)->count();

        $totalStudents = $activeCount + $alumniCount + $mutatedCount;

        $maleCount = (clone $activeQuery)->where('gender', 'L')->count();
        $femaleCount = (clone $activeQuery)->where('gender', 'P')->count();

        $availableGraduationYears = (clone $alumniBaseQuery)
            ->whereNotNull('graduation_year')
            ->distinct()
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year');

        $availableAcademicYears = SchoolSetting::getAvailableAcademicYears();

        $user = auth()->user();
        $canManageMasterData = $user && ($user->isSuperAdmin()
            || $user->hasPermission('manage_master_data')
            || $user->hasRole(['waka_kurikulum', 'kepala_sekolah']));
        $canExport = $canManageMasterData || ($user && $user->isHomeroomTeacher());

        return view('master.students.index', compact(
            'students',
            'classrooms',
            'classroomId',
            'gender',
            'status',
            'search',
            'academicYear',
            'availableAcademicYears',
            'tab',
            'graduationYear',
            'availableGraduationYears',
            'totalStudents',
            'maleCount',
            'femaleCount',
            'activeCount',
            'alumniCount',
            'mutatedCount',
            'sort',
            'direction',
            'canManageMasterData',
            'canExport'
        ));
    }

    public function show(Student $student): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $canManageMasterData = $user->isSuperAdmin()
            || $user->hasPermission('manage_master_data')
            || $user->hasRole(['waka_kurikulum', 'kepala_sekolah']);

        $isAssignedHomeroom = $student->classroom !== null
            && (bool) $student->classroom->is_active
            && $user->isHomeroomTeacher($student->classroom);

        if (! $canManageMasterData && ! $isAssignedHomeroom) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengakses rincian data siswa ini.');
        }

        // Susun payload eksplisit biodata umum
        $studentPayload = [
            'id' => $student->id,
            'name' => $student->name,
            'classroom_id' => $student->classroom_id,
            'nisn' => $student->nisn,
            'nis' => $student->nis,
            'gender' => $student->gender,
            'birth_place' => $student->birth_place,
            'birth_date' => $student->birth_date?->format('Y-m-d'),
            'religion' => $student->religion,
            'address' => $student->address,
            'phone_student' => $student->phone_student,
            'email' => $student->email,
            'entry_year' => $student->entry_year,
            'father_name' => $student->father_name,
            'father_job' => $student->father_job,
            'father_phone' => $student->father_phone,
            'father_birth_year' => $student->father_birth_year,
            'father_education' => $student->father_education,
            'mother_name' => $student->mother_name,
            'mother_job' => $student->mother_job,
            'mother_phone' => $student->mother_phone,
            'mother_birth_year' => $student->mother_birth_year,
            'mother_education' => $student->mother_education,
            'guardian_name' => $student->guardian_name,
            'guardian_job' => $student->guardian_job,
            'guardian_phone' => $student->guardian_phone,
            'guardian_birth_year' => $student->guardian_birth_year,
            'guardian_education' => $student->guardian_education,
            'phone_parent' => $student->phone_parent,
            'status' => $student->status,
            'is_active' => (bool) $student->is_active,
            'graduation_year' => $student->graduation_year,
            'last_classroom_name' => $student->last_classroom_name,
            'distance_to_school_km' => $student->distance_to_school_km,
            'transportation' => $student->transportation,
            'residence_type' => $student->residence_type,
            'school_origin' => $student->school_origin,
            'child_order' => $student->child_order,
            'siblings_count' => $student->siblings_count,
            'height_cm' => $student->height_cm,
            'weight_kg' => $student->weight_kg,
            'head_circumference_cm' => $student->head_circumference_cm,
            'notes' => $student->notes,
        ];

        // Hanya pengelola data pokok / pimpinan yang memperoleh data kependudukan dan finansial sensitif
        if ($canManageMasterData) {
            $studentPayload['nik'] = $student->nik;
            $studentPayload['family_card_no'] = $student->family_card_no;
            $studentPayload['birth_certificate_no'] = $student->birth_certificate_no;
            $studentPayload['father_nik'] = $student->father_nik;
            $studentPayload['mother_nik'] = $student->mother_nik;
            $studentPayload['guardian_nik'] = $student->guardian_nik;
            $studentPayload['father_income'] = $student->father_income;
            $studentPayload['mother_income'] = $student->mother_income;
            $studentPayload['guardian_income'] = $student->guardian_income;
            $studentPayload['bank_name'] = $student->bank_name;
            $studentPayload['bank_account_no'] = $student->bank_account_no;
            $studentPayload['bank_account_name'] = $student->bank_account_name;
            $studentPayload['latitude'] = $student->latitude;
            $studentPayload['longitude'] = $student->longitude;
            $studentPayload['special_needs'] = $student->special_needs;
            $studentPayload['is_pip_eligible'] = $student->is_pip_eligible;
            $studentPayload['pip_reason'] = $student->pip_reason;
            $studentPayload['kip_recipient'] = $student->kip_recipient;
            $studentPayload['kip_number'] = $student->kip_number;
            $studentPayload['kks_number'] = $student->kks_number;
            $studentPayload['kps_number'] = $student->kps_number;
        }

        $latestMutation = $student->latestMutation;
        $mutationPayload = null;
        if ($latestMutation) {
            $mutationPayload = [
                'id' => $latestMutation->id,
                'type' => $latestMutation->type,
                'type_label' => $latestMutation->type_label,
                'type_badge' => $latestMutation->type_badge,
                'mutation_date' => $latestMutation->mutation_date?->translatedFormat('d F Y'),
                'reason_category' => $latestMutation->reason_category,
                'reason_category_label' => $latestMutation->reason_category_label,
                'reason_description' => $latestMutation->reason_description,
                'target_school' => $latestMutation->target_school,
                'letter_number' => $latestMutation->letter_number,
                'attachment_name' => $latestMutation->attachment_name,
                'attachment_path' => (bool) $latestMutation->attachment_path,
                'recorded_by' => $latestMutation->recordedBy?->name ?? 'Sistem',
            ];
        }

        return response()->json([
            'success' => true,
            'student' => $studentPayload,
            'formatted_birth' => $student->formatted_birth,
            'primary_parent' => $student->primary_parent_name,
            'primary_phone' => $student->primary_parent_phone,
            'classroom_name' => $student->classroom?->name ?? ($student->last_classroom_name ?? 'Belum ada kelas'),
            'homeroom_name' => $student->classroom?->homeroomTeacher?->name ?? 'Belum ada wali kelas',
            'status_label' => $student->status_label,
            'status_badge' => $student->status_badge,
            'latest_mutation' => $mutationPayload,
        ]);
    }

    /**
     * Dapatkan atribut data yang diperlukan untuk formulir edit siswa.
     * Hanya dapat diakses oleh pengguna yang berwenang mengelola data master siswa.
     */
    public function editData(Student $student): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->hasPermission('manage_master_data') && ! $user->hasRole(['waka_kurikulum', 'kepala_sekolah']))) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengubah data master siswa.');
        }

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'classroom_id' => $student->classroom_id,
                'nisn' => $student->nisn,
                'nis' => $student->nis,
                'nik' => $student->nik,
                'gender' => $student->gender,
                'birth_place' => $student->birth_place,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'religion' => $student->religion,
                'phone_student' => $student->phone_student,
                'address' => $student->address,
                'father_name' => $student->father_name,
                'father_job' => $student->father_job,
                'father_phone' => $student->father_phone,
                'mother_name' => $student->mother_name,
                'mother_job' => $student->mother_job,
                'mother_phone' => $student->mother_phone,
                'guardian_name' => $student->guardian_name,
                'guardian_job' => $student->guardian_job,
                'guardian_phone' => $student->guardian_phone,
                'phone_parent' => $student->phone_parent,
                'status' => $student->status,
                'graduation_year' => $student->graduation_year,
                'last_classroom_name' => $student->last_classroom_name,
                'entry_year' => $student->entry_year,
                'notes' => $student->notes,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nisn' => 'nullable|string|max:30|unique:students,nisn',
            'nis' => 'nullable|string|max:30',
            'nik' => 'nullable|string|max:30',
            'name' => 'required|string|max:255',
            'gender' => 'required|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'religion' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'status' => 'nullable|string|in:aktif,lulus,mutasi,keluar',
            'graduation_year' => 'nullable|string|max:20',
            'last_classroom_name' => 'nullable|string|max:100',
            'entry_year' => 'nullable|string|max:20',

            // Orang Tua / Wali
            'father_name' => 'nullable|string|max:255',
            'father_job' => 'nullable|string|max:100',
            'father_phone' => 'nullable|string|max:50',

            'mother_name' => 'nullable|string|max:255',
            'mother_job' => 'nullable|string|max:100',
            'mother_phone' => 'nullable|string|max:50',

            'guardian_name' => 'nullable|string|max:255',
            'guardian_job' => 'nullable|string|max:100',
            'guardian_phone' => 'nullable|string|max:50',

            'phone_parent' => 'nullable|string|max:50',
            'phone_student' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $status = $validated['status'] ?? 'aktif';
        $validated['status'] = $status;
        $validated['is_active'] = ($status === 'aktif');

        if ($status !== 'aktif' && empty($validated['last_classroom_name']) && ! empty($validated['classroom_id'])) {
            $cls = Classroom::find($validated['classroom_id']);
            $validated['last_classroom_name'] = $cls?->name;
        }

        // Auto-assign phone_parent jika belum diisi tetapi phone ayah/ibu/wali ada
        if (empty($validated['phone_parent'])) {
            $validated['phone_parent'] = $validated['father_phone'] ?? $validated['mother_phone'] ?? $validated['guardian_phone'] ?? null;
        }

        $student = Student::create($validated);

        // Sinkronisasi riwayat rombel (StudentClassroom) untuk tahun pelajaran aktif
        $activeAy = AcademicYear::getActive() ?? AcademicYear::where('name', SchoolSetting::getSetting()?->academic_year)->first();
        if ($activeAy && ! empty($validated['classroom_id'])) {
            $cls = Classroom::find($validated['classroom_id']);
            StudentClassroom::updateOrCreate([
                'student_id' => $student->id,
                'academic_year_id' => $activeAy->id,
            ], [
                'classroom_id' => $validated['classroom_id'],
                'status' => $student->status,
                'academic_year_name' => $activeAy->name,
                'classroom_name' => $cls?->name,
                'is_repeating' => false,
            ]);
        }

        return back()->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'nisn' => 'nullable|string|max:30|unique:students,nisn,'.$student->id,
            'nis' => 'nullable|string|max:30',
            'nik' => 'nullable|string|max:30',
            'name' => 'required|string|max:255',
            'gender' => 'required|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'religion' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'status' => 'nullable|string|in:aktif,lulus,mutasi,keluar',
            'graduation_year' => 'nullable|string|max:20',
            'last_classroom_name' => 'nullable|string|max:100',
            'entry_year' => 'nullable|string|max:20',

            // Orang Tua / Wali
            'father_name' => 'nullable|string|max:255',
            'father_job' => 'nullable|string|max:100',
            'father_phone' => 'nullable|string|max:50',

            'mother_name' => 'nullable|string|max:255',
            'mother_job' => 'nullable|string|max:100',
            'mother_phone' => 'nullable|string|max:50',

            'guardian_name' => 'nullable|string|max:255',
            'guardian_job' => 'nullable|string|max:100',
            'guardian_phone' => 'nullable|string|max:50',

            'phone_parent' => 'nullable|string|max:50',
            'phone_student' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $status = $validated['status'] ?? ($request->has('is_active') && ! $request->boolean('is_active') ? 'keluar' : $student->status ?? 'aktif');
        $validated['status'] = $status;
        $validated['is_active'] = ($status === 'aktif');

        if ($status !== 'aktif' && empty($validated['last_classroom_name'])) {
            $clsId = $validated['classroom_id'] ?? $student->classroom_id;
            if ($clsId) {
                $cls = Classroom::find($clsId);
                $validated['last_classroom_name'] = $cls?->name;
            }
        }

        if (empty($validated['phone_parent'])) {
            $validated['phone_parent'] = $validated['father_phone'] ?? $validated['mother_phone'] ?? $validated['guardian_phone'] ?? null;
        }

        $student->update($validated);

        // Sinkronisasi riwayat rombel jika ada perubahan classroom_id atau status
        $activeAy = AcademicYear::getActive() ?? AcademicYear::where('name', SchoolSetting::getSetting()?->academic_year)->first();
        if ($activeAy) {
            $effectiveClassroomId = $student->classroom_id;
            if ($effectiveClassroomId) {
                $cls = Classroom::find($effectiveClassroomId);
                StudentClassroom::updateOrCreate([
                    'student_id' => $student->id,
                    'academic_year_id' => $activeAy->id,
                ], [
                    'classroom_id' => $effectiveClassroomId,
                    'status' => $student->status,
                    'academic_year_name' => $activeAy->name,
                    'classroom_name' => $cls?->name,
                ]);
            } elseif (in_array($student->status, ['mutasi', 'keluar', 'lulus'])) {
                StudentClassroom::where('student_id', $student->id)
                    ->where('academic_year_id', $activeAy->id)
                    ->update(['status' => $student->status]);
            }
        }

        return back()->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $name = $student->name;
        $student->delete();

        return back()->with('success', "Data siswa {$name} berhasil dihapus.");
    }

    public function downloadTemplate(): StreamedResponse
    {
        $filename = 'template-import-siswa-lengkap.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM for UTF-8

            fputcsv($handle, [
                'NISN',
                'NIS',
                'NIK',
                'Nama Siswa',
                'Kelas / Rombel',
                'Jenis Kelamin (L/P)',
                'Tempat Lahir',
                'Tanggal Lahir (YYYY-MM-DD atau DD/MM/YYYY)',
                'Agama',
                'Alamat Lengkap',
                'Nama Ayah',
                'Pekerjaan Ayah',
                'No HP Ayah',
                'Nama Ibu',
                'Pekerjaan Ibu',
                'No HP Ibu',
                'Nama Wali',
                'Pekerjaan Wali',
                'No HP Wali',
                'No HP Kontak Ortu (Utama)',
                'No HP Siswa',
                'Catatan',
            ]);

            // Sample rows
            fputcsv($handle, [
                '0071234567',
                '202401',
                '3506123456780001',
                'Ahmad Fauzi',
                'X TKRO 1',
                'L',
                'Kediri',
                '15/05/2008',
                'Islam',
                'Jl. Raya Kras No. 12, Kediri',
                'Budi Santoso',
                'Wiraswasta',
                '081234567890',
                'Siti Aminah',
                'Ibu Rumah Tangga',
                '081234567891',
                '',
                '',
                '',
                '081234567890',
                '085678901234',
                'Siswa aktif',
            ]);

            fputcsv($handle, [
                '0079876543',
                '202402',
                '3506123456780002',
                'Dewi Lestari',
                'X TKRO 1',
                'P',
                'Kediri',
                '20/08/2008',
                'Islam',
                'Dsn. Sumberarum RT 02 RW 01, Kras, Kediri',
                'Suranto',
                'PNS',
                '081398765432',
                'Sri Wahyuni',
                'Guru',
                '081398765433',
                '',
                '',
                '',
                '081398765432',
                '087812345678',
                'Penerima PIP',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'academic_year' => 'nullable|string|max:20',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        $defaultClassroomId = $request->input('classroom_id') ? (int) $request->input('classroom_id') : null;

        $academicYearContext = app(AcademicYearContext::class);
        try {
            $academicYear = $request->filled('academic_year')
                ? $academicYearContext->resolveExplicitYear(trim($request->input('academic_year')))
                : $academicYearContext->getOperationalYear()->name;
        } catch (\InvalidArgumentException) {
            return back()->with('error', 'Gagal mengimpor data siswa: Tahun pelajaran target tidak terdaftar.');
        } catch (\Throwable) {
            return back()->with('error', 'Gagal mengimpor data siswa: Konfigurasi tahun pelajaran operasional tidak tersedia.');
        }

        try {
            $result = $this->importService->import($path, $ext, $defaultClassroomId, $academicYear);

            if (! $result['success']) {
                $err = implode(', ', array_slice($result['errors'], 0, 5));

                return back()->with('error', 'Gagal mengimpor data siswa: '.($err ?: 'Format file tidak sesuai.'));
            }

            $msg = "Berhasil mengimpor {$result['imported_count']} siswa baru.";
            if (count($result['updated']) > 0) {
                $msg .= ' Sebanyak '.count($result['updated']).' data siswa diperbarui.';
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Gagal mengimpor data siswa: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Gagal mengimpor data siswa: Terjadi kesalahan sistem saat memproses file.');
        }
    }

    public function importDapodik(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'academic_year' => 'nullable|string|max:20',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'auto_graduate_grade_12' => 'nullable|boolean',
            'auto_mutasi_missing_students' => 'nullable|boolean',
            'auto_create_classes' => 'nullable|boolean',
            'set_as_active_year' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $origName = $file->getClientOriginalName();

        $academicYearContext = app(AcademicYearContext::class);
        $ayRecord = null;
        $startYear = null;
        $endYear = null;

        if ($request->filled('academic_year')) {
            $inputYear = trim($request->input('academic_year'));

            // 1. Validasi format YYYY/YYYY
            if (! preg_match('/^(\d{4})\/(\d{4})$/', $inputYear, $matches)) {
                return back()->with('error', 'Gagal memproses file Dapodik: Format tahun pelajaran target tidak valid. Format harus YYYY/YYYY (contoh: 2026/2027).');
            }

            $startYear = (int) $matches[1];
            $endYear = (int) $matches[2];

            // 2. Validasi rentang tahun: tahun akhir harus tepat 1 tahun setelah tahun awal
            if ($endYear !== $startYear + 1) {
                return back()->with('error', "Gagal memproses file Dapodik: Rentang tahun pelajaran tidak valid. Tahun akhir ({$endYear}) harus tepat satu tahun setelah tahun awal ({$startYear}).");
            }

            // 3. Batas kewajaran tahun kalender pendidikan
            if ($startYear < 2000 || $startYear > 2099) {
                return back()->with('error', 'Gagal memproses file Dapodik: Tahun pelajaran di luar rentang kalender pendidikan yang diizinkan (2000-2099).');
            }

            $academicYear = $inputYear;
            $ayRecord = AcademicYear::where('name', $academicYear)->first();
        } else {
            try {
                $operationalYear = $academicYearContext->getOperationalYear();
                $academicYear = $operationalYear->name;
                $ayRecord = $operationalYear;
                [$startYear, $endYear] = $this->parseAcademicYear($academicYear);
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal memproses sinkronisasi Dapodik: Konfigurasi tahun pelajaran operasional tidak tersedia ('.$e->getMessage().').');
            }
        }

        // 4. Periksa apakah tahun target sedang dikunci/diarsipkan
        if ($ayRecord && $ayRecord->is_locked) {
            return back()->with('error', "Gagal memproses file Dapodik: Tahun pelajaran {$academicYear} sedang dikunci (arsip permanen) dan tidak dapat dimodifikasi.");
        }

        // 5. Jika tahun belum terdaftar, buat record baru dengan data kalender standar
        if (! $ayRecord) {
            $ayRecord = AcademicYear::create([
                'name' => $academicYear,
                'semester' => 'Ganjil',
                'start_date' => "{$startYear}-07-01",
                'end_date' => "{$endYear}-06-30",
                'is_active' => false,
                'is_locked' => false,
                'description' => 'Dibuat otomatis dari sinkronisasi Dapodik',
            ]);
        }

        // Simpan file asli Dapodik secara permanen di storage server sebagai arsip induk
        $safeYear = str_replace(['/', '\\'], '_', $academicYear);
        $safeOrigName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
        $savedFilename = "dapodik_{$safeYear}_".date('Ymd_His')."_{$safeOrigName}";
        $storedPath = $file->storeAs('dapodik_archives', $savedFilename, 'local');
        $path = Storage::disk('local')->path($storedPath);

        $defaultClassroomId = $request->input('classroom_id') ? (int) $request->input('classroom_id') : null;

        $options = [
            'auto_graduate_grade_12' => $request->boolean('auto_graduate_grade_12', false),
            'auto_mutasi_missing_students' => $request->boolean('auto_mutasi_missing_students', false),
            'auto_create_classes' => $request->boolean('auto_create_classes', true),
        ];

        try {
            $result = $this->dapodikImportService->import($path, $ext, $defaultClassroomId, $academicYear, $options);

            if (! $result['success']) {
                $err = implode(', ', array_slice($result['errors'], 0, 5));

                return back()->with('error', 'Gagal memproses file Dapodik: '.($err ?: 'Pastikan file adalah ekspor resmi "Daftar PD" dari Dapodik.'));
            }

            // Aktivasi tahun ajaran jika opsi centang dipilih
            $activationFailed = false;
            $activationErrorMessage = null;
            if ($request->boolean('set_as_active_year')) {
                try {
                    $ayRecord->setAsActive();
                    session([
                        'active_academic_year' => $academicYear,
                        'academic_year' => $academicYear,
                    ]);
                } catch (\Throwable $e) {
                    $activationFailed = true;
                    $activationErrorMessage = $e->getMessage();
                    Log::error('Sinkronisasi Dapodik berhasil disimpan namun aktivasi tahun ajaran gagal: '.$e->getMessage(), [
                        'academic_year' => $academicYear,
                        'exception' => $e,
                    ]);
                }
            }

            $parts = [];
            if ($result['imported_count'] > 0) {
                $parts[] = "{$result['imported_count']} siswa baru ditambahkan";
            }
            if ($result['promoted_count'] > 0) {
                $parts[] = "{$result['promoted_count']} siswa naik/pindah rombel";
            }
            if ($result['updated_count'] > 0) {
                $parts[] = "{$result['updated_count']} data siswa diperbarui";
            }
            if ($result['graduated_count'] > 0) {
                $parts[] = "{$result['graduated_count']} siswa kelas XII lama otomatis dialihkan ke status Alumni";
            }
            if (($result['mutated_count'] ?? 0) > 0) {
                $parts[] = "{$result['mutated_count']} siswa kelas X/XI yang tidak ada di file dialihkan ke status Mutasi/Keluar";
            }

            $summary = ! empty($parts) ? implode(', ', $parts) : 'Data telah sesuai dan mutakhir';

            // Catat ke log aktivitas dengan path file arsip
            ActivityLogger::log('import_dapodik', "Sinkronisasi Dapodik {$academicYear} dari file {$origName}", null, [
                'academic_year' => $academicYear,
                'original_filename' => $origName,
                'stored_archive_path' => $storedPath,
                'summary' => $summary,
                'activation_status' => $request->boolean('set_as_active_year') ? ($activationFailed ? 'failed' : 'active') : 'unchanged',
            ]);

            // Jika impor data berhasil namun aktivasi gagal, sampaikan pesan jujur dan tidak menyesatkan
            if ($activationFailed) {
                $msg = "Sinkronisasi data siswa dari Dapodik berhasil ({$summary}), NAMUN penetapan Tahun Pelajaran {$academicYear} sebagai tahun aktif sekolah gagal: {$activationErrorMessage}. Data siswa telah tersimpan, silakan tetapkan status tahun aktif secara manual melalui menu Tahun Pelajaran.";

                return back()->with('warning', $msg);
            }

            $msg = "Sinkronisasi Dapodik & Kenaikan Kelas Berhasil! ({$summary}).";
            if ($request->boolean('set_as_active_year')) {
                $msg .= " Tahun Pelajaran {$academicYear} kini resmi aktif.";
            }
            if ($result['skipped_count'] > 0) {
                $msg .= " Catatan: {$result['skipped_count']} baris dilewati karena format tidak lengkap.";
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Gagal memproses file Dapodik: '.$e->getMessage(), [
                'file' => $origName,
                'exception' => $e,
            ]);

            return back()->with('error', 'Gagal memproses file Dapodik: Terjadi kesalahan sistem saat memproses file.');
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $canManageMasterData = $user->isSuperAdmin()
            || $user->hasPermission('manage_master_data')
            || $user->hasRole(['waka_kurikulum', 'kepala_sekolah']);

        $isHomeroom = $user->isHomeroomTeacher();

        if (! $canManageMasterData && ! $isHomeroom) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengekspor data siswa.');
        }

        $tab = $request->query('tab', 'aktif');
        if (! in_array($tab, ['aktif', 'alumni', 'mutasi'], true)) {
            $tab = 'aktif';
        }

        // Wali kelas hanya memiliki hak akses ekspor pada siswa aktif rombel binaannya
        if (! $canManageMasterData && $tab !== 'aktif') {
            abort(403, 'Akses ekspor data alumni dan mutasi hanya diperuntukkan bagi pengelola data pokok.');
        }

        $setting = SchoolSetting::getSetting();
        $requestedAy = $request->query('academic_year');

        // Validasi tahun ajaran terhadap data tahun ajaran sistem
        if ($requestedAy) {
            $activeAy = AcademicYear::where('name', $requestedAy)->first();
            if (! $activeAy) {
                abort(404, 'Tahun ajaran tidak valid.');
            }
            $academicYear = $requestedAy;
        } else {
            $academicYear = session('active_academic_year', $setting->academic_year ?? SchoolSetting::getMasterYear());
            $activeAy = AcademicYear::where('name', $academicYear)->first();
        }

        $classroomId = $request->query('classroom_id');

        // Pembatasan cakupan rombel dan tahun ajaran untuk wali kelas (hanya rombel aktif yang sah)
        if (! $canManageMasterData) {
            $assignedClassrooms = Classroom::where('is_active', true)
                ->where('homeroom_teacher_id', $user->id)
                ->get();

            if ($assignedClassrooms->isEmpty()) {
                abort(403, 'Anda belum ditugaskan sebagai wali kelas pada rombel aktif.');
            }

            $assignedClassroomIds = $assignedClassrooms->pluck('id')->all();

            // Validasi tahun ajaran untuk wali kelas: harus memiliki rombel aktif pada tahun ajaran yang diminta
            if ($requestedAy) {
                $hasActiveInRequestedYear = $assignedClassrooms->contains(fn ($c) => $c->academic_year === $requestedAy);
                if (! $hasActiveInRequestedYear) {
                    abort(403, 'Anda tidak memiliki penugasan wali kelas aktif pada tahun ajaran yang diminta.');
                }
            }

            // Validasi parameter classroom_id
            if ($classroomId) {
                if (! in_array((int) $classroomId, $assignedClassroomIds, true)) {
                    abort(403, 'Anda tidak memiliki wewenang untuk mengekspor data rombel ini.');
                }
                $targetClassroomIds = [(int) $classroomId];
            } else {
                $targetClassroomIds = $assignedClassrooms
                    ->when($requestedAy, fn ($col) => $col->where('academic_year', $requestedAy))
                    ->pluck('id')
                    ->all();
            }
        } else {
            // Untuk master data manager: jika classroom_id diberikan, pastikan rombel ada
            if ($classroomId) {
                $targetClassroom = Classroom::find($classroomId);
                if (! $targetClassroom) {
                    abort(404, 'Rombel tidak ditemukan.');
                }
                $targetClassroomIds = [(int) $classroomId];
            } else {
                $targetClassroomIds = null;
            }
        }

        $graduationYear = $request->query('graduation_year');
        $gender = $request->query('gender');
        $status = $request->query('status');
        $search = $request->query('search');

        [$startYear, $endYear] = $this->parseAcademicYear($academicYear);
        $masterYear = SchoolSetting::getMasterYear();
        $isCurrentOrMasterYear = ($academicYear === $masterYear || (bool) $activeAy?->is_active);

        $query = Student::with([
            'classroom',
            'latestMutation',
            'studentClassrooms' => function ($q) use ($activeAy) {
                if ($activeAy) {
                    $q->where('academic_year_id', $activeAy->id);
                }
            },
        ])->orderBy('name');

        if ($tab === 'alumni') {
            $this->scopeAlumniForYear($query, $academicYear, $activeAy, $startYear, $graduationYear, $isCurrentOrMasterYear);
        } elseif ($tab === 'mutasi') {
            $this->scopeMutasiForYear($query, $academicYear, $activeAy, $startYear, $endYear, $status, $isCurrentOrMasterYear);
        } else {
            if ($targetClassroomIds !== null) {
                $query->where(function ($cq) use ($targetClassroomIds, $activeAy) {
                    if ($activeAy) {
                        $cq->whereHas('studentClassrooms', function ($sq) use ($targetClassroomIds, $activeAy) {
                            $sq->where('academic_year_id', $activeAy->id)
                                ->whereIn('classroom_id', $targetClassroomIds)
                                ->where('status', 'aktif');
                        });
                    }
                    $cq->orWhereIn('classroom_id', $targetClassroomIds);
                });
            } else {
                $this->scopeActiveForYear($query, $academicYear, $activeAy, $startYear);
            }
        }

        if ($gender && in_array($gender, ['L', 'P'], true)) {
            $query->where('gender', $gender);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        $students = $query->get();
        $dateStr = date('Ymd_His');
        $filename = "data-siswa-{$tab}-smk-pgri-kras-{$dateStr}.csv";

        return response()->streamDownload(function () use ($students, $tab, $canManageMasterData) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM for UTF-8

            // Header kolom dibedakan: pengelola data pokok mendapatkan NIK, wali kelas tidak mendapatkan NIK
            if ($canManageMasterData) {
                $headers = [
                    'No',
                    'NISN',
                    'NIS',
                    'NIK',
                    'Nama Siswa',
                    'Jenis Kelamin',
                    'Kelas / Rombel',
                    'Tahun Lulus / Angkatan',
                    'Tempat Lahir',
                    'Tanggal Lahir',
                    'Agama',
                    'Alamat Lengkap',
                    'Nama Ayah',
                    'Pekerjaan Ayah',
                    'No HP Ayah',
                    'Nama Ibu',
                    'Pekerjaan Ibu',
                    'No HP Ibu',
                    'Nama Wali',
                    'Pekerjaan Wali',
                    'No HP Wali',
                    'Kontak Ortu Utama',
                    'No HP Siswa',
                    'Status',
                    'Catatan',
                ];
            } else {
                $headers = [
                    'No',
                    'NISN',
                    'NIS',
                    'Nama Siswa',
                    'Jenis Kelamin',
                    'Kelas / Rombel',
                    'Tahun Lulus / Angkatan',
                    'Tempat Lahir',
                    'Tanggal Lahir',
                    'Agama',
                    'Alamat Lengkap',
                    'Nama Ayah',
                    'Pekerjaan Ayah',
                    'No HP Ayah',
                    'Nama Ibu',
                    'Pekerjaan Ibu',
                    'No HP Ibu',
                    'Nama Wali',
                    'Pekerjaan Wali',
                    'No HP Wali',
                    'Kontak Ortu Utama',
                    'No HP Siswa',
                    'Status',
                    'Catatan',
                ];
            }

            fputcsv($handle, $headers);

            foreach ($students as $index => $s) {
                $enrolment = $s->studentClassrooms->first();
                $className = $enrolment?->classroom?->name
                    ?? $enrolment?->classroom_name
                    ?? ($s->classroom?->name ?? ($s->last_classroom_name ?? '-'));

                $statusLabel = match ($tab) {
                    'aktif' => 'Aktif',
                    'alumni' => 'Alumni / Lulus',
                    'mutasi' => $s->status_label,
                    default => $s->status_label,
                };

                if ($canManageMasterData) {
                    $row = [
                        $index + 1,
                        $s->nisn,
                        $s->nis,
                        $s->nik,
                        $s->name,
                        $s->gender === 'P' ? 'Perempuan' : 'Laki-laki',
                        $className,
                        $s->graduation_year ?: ($s->entry_year ? "Angkatan {$s->entry_year}" : '-'),
                        $s->birth_place,
                        $s->birth_date?->format('d/m/Y'),
                        $s->religion,
                        $s->address,
                        $s->father_name,
                        $s->father_job,
                        $s->father_phone,
                        $s->mother_name,
                        $s->mother_job,
                        $s->mother_phone,
                        $s->guardian_name,
                        $s->guardian_job,
                        $s->guardian_phone,
                        $s->phone_parent,
                        $s->phone_student,
                        $statusLabel,
                        $s->notes,
                    ];
                } else {
                    $row = [
                        $index + 1,
                        $s->nisn,
                        $s->nis,
                        $s->name,
                        $s->gender === 'P' ? 'Perempuan' : 'Laki-laki',
                        $className,
                        $s->graduation_year ?: ($s->entry_year ? "Angkatan {$s->entry_year}" : '-'),
                        $s->birth_place,
                        $s->birth_date?->format('d/m/Y'),
                        $s->religion,
                        $s->address,
                        $s->father_name,
                        $s->father_job,
                        $s->father_phone,
                        $s->mother_name,
                        $s->mother_job,
                        $s->mother_phone,
                        $s->guardian_name,
                        $s->guardian_job,
                        $s->guardian_phone,
                        $s->phone_parent,
                        $s->phone_student,
                        $statusLabel,
                        $s->notes,
                    ];
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $classroomId = $request->input('classroom_id');
        $academicYear = $request->input('academic_year') ?: session('active_academic_year');

        if (strlen($q) < 2 && ! $classroomId) {
            return response()->json([]);
        }

        $query = Student::with(['classroom.homeroomTeacher']);

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        } elseif ($academicYear) {
            $activeAy = AcademicYear::where('name', $academicYear)->first();
            [$startYear] = $this->parseAcademicYear($academicYear);
            $this->scopeActiveForYear($query, $academicYear, $activeAy, $startYear);
        } else {
            $query->where('is_active', true);
        }

        if (strlen($q) >= 2) {
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                    ->orWhere('nisn', 'like', "%{$q}%")
                    ->orWhere('nis', 'like', "%{$q}%")
                    ->orWhere('nik', 'like', "%{$q}%");
            });
        }

        $results = $query->limit(15)->get()->map(function ($s) {
            $homeroomName = $s->classroom?->homeroomTeacher?->formatted_short_name
                ?: ($s->classroom?->homeroomTeacher?->name ?? '-');

            return [
                'id' => $s->id,
                'name' => $s->name,
                'nisn' => $s->nisn,
                'nis' => $s->nis,
                'nik' => $s->nik,
                'classroom_id' => $s->classroom_id,
                'classroom_name' => $s->classroom?->name ?? '-',
                'homeroom_teacher' => $homeroomName,
                'phone_parent' => $s->phone_parent,
                'phone_student' => $s->phone_student,
                'primary_parent' => $s->primary_parent_name,
                'text' => "{$s->name} (".($s->classroom?->name ?? '-').($s->nisn ? " - {$s->nisn}" : '').')',
            ];
        });

        return response()->json($results);
    }

    /**
     * Catat Mutasi / Pengunduran Diri Siswa beserta alasan & dokumen bukti.
     */
    public function recordMutation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'type' => 'required|in:mutasi,keluar,dikeluarkan,drop_out',
            'mutation_date' => 'required|date',
            'reason_category' => 'required|string|max:50',
            'reason_description' => 'nullable|string',
            'target_school' => 'nullable|string|max:255',
            'letter_number' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $student = Student::findOrFail($validated['student_id']);

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $attachmentPath = $file->store('student_mutations', 'local');
        }

        DB::transaction(function () use ($student, $validated, $attachmentPath, $attachmentName) {
            StudentMutation::create([
                'student_id' => $student->id,
                'classroom_id' => $student->classroom_id,
                'type' => $validated['type'],
                'mutation_date' => $validated['mutation_date'],
                'reason_category' => $validated['reason_category'],
                'reason_description' => $validated['reason_description'] ?? null,
                'target_school' => $validated['target_school'] ?? null,
                'letter_number' => $validated['letter_number'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'recorded_by' => auth()->id(),
            ]);

            $newStatus = in_array($validated['type'], ['mutasi', 'keluar']) ? $validated['type'] : 'keluar';

            $student->update([
                'status' => $newStatus,
                'is_active' => false,
                'last_classroom_name' => $student->classroom?->name ?? $student->last_classroom_name,
                'classroom_id' => null,
            ]);

            // Sinkronkan status pada riwayat student_classrooms
            $activeAy = AcademicYear::getActive() ?? AcademicYear::where('name', SchoolSetting::getSetting()?->academic_year)->first();
            if ($activeAy) {
                StudentClassroom::where('student_id', $student->id)
                    ->where('academic_year_id', $activeAy->id)
                    ->update(['status' => $newStatus]);
            }
        });

        return redirect()->route('students.index', ['tab' => 'mutasi'])
            ->with('success', "Data mutasi/keluar untuk siswa {$student->name} berhasil dicatat beserta dokumen pendukung.");
    }

    /**
     * Batalkan data mutasi dan kembalikan siswa ke status Aktif.
     */
    public function destroyMutation(StudentMutation $mutation): RedirectResponse
    {
        $student = $mutation->student;

        DB::transaction(function () use ($mutation, $student) {
            if ($mutation->attachment_path) {
                if (Storage::disk('local')->exists($mutation->attachment_path)) {
                    Storage::disk('local')->delete($mutation->attachment_path);
                } elseif (Storage::disk('public')->exists($mutation->attachment_path)) {
                    Storage::disk('public')->delete($mutation->attachment_path);
                }
            }

            $prevClassroomId = $mutation->classroom_id;
            $mutation->delete();

            if ($student && $student->mutations()->count() === 0) {
                $restoredClassroomId = $prevClassroomId ?: $student->classroom_id;
                $student->update([
                    'status' => 'aktif',
                    'is_active' => true,
                    'classroom_id' => $restoredClassroomId,
                ]);

                $activeAy = AcademicYear::getActive() ?? AcademicYear::where('name', SchoolSetting::getSetting()?->academic_year)->first();
                if ($activeAy) {
                    $cls = $restoredClassroomId ? Classroom::find($restoredClassroomId) : null;
                    StudentClassroom::updateOrCreate([
                        'student_id' => $student->id,
                        'academic_year_id' => $activeAy->id,
                    ], [
                        'classroom_id' => $restoredClassroomId,
                        'status' => 'aktif',
                        'academic_year_name' => $activeAy->name,
                        'classroom_name' => $cls?->name,
                    ]);
                }
            }
        });

        return back()->with('success', "Riwayat mutasi untuk {$student?->name} berhasil dibatalkan dan status siswa dikembalikan ke Aktif.");
    }

    /**
     * Unduh berkas bukti pendukung mutasi / surat pengunduran diri.
     */
    public function downloadMutationAttachment(StudentMutation $mutation)
    {
        $path = $mutation->attachment_path;
        if (! $path || str_contains($path, '..')) {
            return back()->with('error', 'Berkas bukti pendukung tidak ditemukan pada server.');
        }

        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if (! $disk) {
            return back()->with('error', 'Berkas bukti pendukung tidak ditemukan pada server.');
        }

        return Storage::disk($disk)->download($path, $mutation->attachment_name ?: 'dokumen-mutasi.pdf');
    }
}
