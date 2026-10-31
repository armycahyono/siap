<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\ActiveUserController;
use App\Http\Controllers\ActivityCategoryController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AppFeedbackController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Attendance\AdmsController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\DeviceManagementController;
use App\Http\Controllers\Attendance\GeotagAttendanceController;
use App\Http\Controllers\Attendance\GtkPermitController;
use App\Http\Controllers\Attendance\ManualGtkAttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Bk\BkCareerAnalyticsController;
use App\Http\Controllers\Bk\BkCareerProfileController;
use App\Http\Controllers\Bk\BkDashboardController;
use App\Http\Controllers\Bk\BkGroupController;
use App\Http\Controllers\Bk\BkReferralController;
use App\Http\Controllers\Bk\BkServiceController;
use App\Http\Controllers\Bk\BkStudentHistoryController;
use App\Http\Controllers\Bk\BkStudentRecordController;
use App\Http\Controllers\CbtIntegrationController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CurriculumCalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\GtkDecreeController;
use App\Http\Controllers\HomeroomAttendanceRecapController;
use App\Http\Controllers\HomeroomStudentPermitController;
use App\Http\Controllers\LessonScheduleController;
use App\Http\Controllers\Master\StudentController;
use App\Http\Controllers\Master\StudentPromotionController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\NotepadController;
use App\Http\Controllers\Piket\DisciplineDashboardController;
use App\Http\Controllers\Piket\PiketCalendarController;
use App\Http\Controllers\Piket\PiketDashboardController;
use App\Http\Controllers\Piket\PiketDutySwapController;
use App\Http\Controllers\Piket\PiketGuestController;
use App\Http\Controllers\Piket\PiketIncidentController;
use App\Http\Controllers\Piket\PiketInvalController;
use App\Http\Controllers\Piket\PiketJournalController;
use App\Http\Controllers\Piket\PiketPermitController;
use App\Http\Controllers\Piket\PiketReportController;
use App\Http\Controllers\Piket\PiketScheduleController;
use App\Http\Controllers\Piket\PiketStudentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\RubricController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchoolActivityController;
use App\Http\Controllers\SchoolStakeholderController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SmartSearchController;
use App\Http\Controllers\StructuralPositionController;
use App\Http\Controllers\StudentVisitationController;
use App\Http\Controllers\SubjectAttendanceController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SupervisionConductController;
use App\Http\Controllers\SupervisionDashboardController;
use App\Http\Controllers\TeacherAssignmentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\TelegramBotWebhookController;
use App\Http\Controllers\TimetableUploadController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Standalone Mobile Client (SPA Client, PWA & APK Shell)
Route::get('/mobile', [MobileController::class, 'index'])->name('mobile.index');
Route::get('/mobile/{path}', [MobileController::class, 'asset'])->where('path', '.*')->name('mobile.asset');
Route::get('/verifikasi/{token}', [VerificationController::class, 'verify'])->name('verify.supervision');
Route::get('/piket/perizinan/{permit}/slip-cetak', [PiketPermitController::class, 'printSlip'])
    ->name('piket.permits.signed-slip')
    ->middleware('signed');
Route::match(['GET', 'POST'], '/telegram/webhook', [TelegramBotWebhookController::class, 'handle'])->name('telegram.webhook');

// Jalur Komunikasi Mesin Absensi (ADMS / IClock Protocol)
Route::match(['GET', 'POST'], '/iclock/cdata', [AdmsController::class, 'cdata'])->name('iclock.cdata');
Route::get('/iclock/getrequest', [AdmsController::class, 'getrequest'])->name('iclock.getrequest');
Route::post('/iclock/devicecmd', [AdmsController::class, 'devicecmd'])->name('iclock.devicecmd');
Route::post('/', [AdmsController::class, 'rootPush'])->name('attendance.root.push');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tugas', [DashboardController::class, 'tasks'])->name('tasks');

    // Profile & Password
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::get('/profile/edit', [AuthController::class, 'editProfile'])->name('profile.edit');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');

    // Global Academic Year Context Switcher
    Route::post('academic-year/switch', [AcademicYearController::class, 'switch'])->name('academic-year.switch');
    Route::match(['GET', 'POST'], 'academic-year/reset', [AcademicYearController::class, 'reset'])->name('academic-year.reset');

    // Live Smart Search API
    Route::get('smart-search', [SmartSearchController::class, 'search'])->name('smart-search');

    // Data Guru (Dapat diakses / dilihat oleh semua pengguna terdaftar)
    Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
    Route::middleware('permission:manage_teachers')->group(function () {
        Route::get('teachers/template-import', [TeacherController::class, 'downloadTemplate'])->name('teachers.template');
        Route::post('teachers/import', [TeacherController::class, 'import'])->name('teachers.import');
        Route::get('teachers/create', [TeacherController::class, 'create'])->name('teachers.create');
        Route::post('teachers', [TeacherController::class, 'store'])->name('teachers.store');
        Route::get('teachers/{teacher}/edit', [TeacherController::class, 'edit'])->name('teachers.edit');
        Route::put('teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
        Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');
    });
    Route::get('teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show')->whereNumber('teacher');

    // Data Siswa (Dapat diakses / dilihat oleh semua pengguna terdaftar)
    Route::get('siswa', [StudentController::class, 'index'])->name('students.index');
    Route::get('siswa/ekspor', [StudentController::class, 'export'])->name('students.export');
    Route::get('siswa/search', [StudentController::class, 'search'])->name('students.search');
    Route::get('siswa/search-api', [StudentController::class, 'searchApi'])->name('students.search-api');
    Route::get('siswa/template-import', [StudentController::class, 'downloadTemplate'])->name('students.template');
    Route::get('siswa/{student}', [StudentController::class, 'show'])->name('students.show')->whereNumber('student');

    // Akses Berkas Tanda Tangan Digital Terotorisasi (FND-04)
    Route::get('structural-positions/{structuralPosition}/signature', [StructuralPositionController::class, 'viewSignature'])->name('structural-positions.signature');
    Route::get('stakeholders/{stakeholder}/signature', [SchoolStakeholderController::class, 'viewSignature'])->name('stakeholders.signature');

    // Data Pokok (Tahun Ajaran, Jurusan, Tingkat & Rombel, Mata Pelajaran, Penugasan Guru, Pejabat)
    Route::middleware('permission:manage_master_data')->group(function () {
        // Kenaikan & Kelulusan Kelas (Alumni) & Pindah Rombel
        Route::get('siswa/kenaikan-kelas', [StudentPromotionController::class, 'index'])->name('students.promotions.index');
        Route::post('siswa/kenaikan-kelas/proses', [StudentPromotionController::class, 'process'])->name('students.promotions.process');
        Route::post('siswa/kenaikan-kelas/rollover', [StudentPromotionController::class, 'executeRollover'])->name('students.promotions.rollover');
        Route::post('siswa/kenaikan-kelas/rollback/{log}', [StudentPromotionController::class, 'rollbackRollover'])->name('students.promotions.rollback');
        Route::post('siswa/pindah-rombel', [StudentPromotionController::class, 'transferProcess'])->name('students.transfers.process');
        // Tahun Ajaran
        Route::get('tahun-ajaran', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::post('tahun-ajaran', [AcademicYearController::class, 'store'])->name('academic-years.store');
        Route::put('tahun-ajaran/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
        Route::post('tahun-ajaran/{academicYear}/set-active', [AcademicYearController::class, 'setActive'])->name('academic-years.set-active');
        Route::post('tahun-ajaran/{academicYear}/toggle-lock', [AcademicYearController::class, 'toggleLock'])->name('academic-years.toggle-lock');
        Route::delete('tahun-ajaran/{academicYear}', [AcademicYearController::class, 'destroy'])->name('academic-years.destroy');

        Route::resource('departments', DepartmentController::class)->except(['create', 'show', 'edit']);
        Route::resource('subjects', SubjectController::class)->except(['create', 'show', 'edit']);
        Route::resource('classrooms', ClassroomController::class)->except(['create', 'show', 'edit']);
        Route::post('classrooms/grade-levels', [ClassroomController::class, 'storeGradeLevel'])->name('classrooms.grade-levels.store');
        Route::resource('structural-positions', StructuralPositionController::class)->except(['create', 'show', 'edit']);
        Route::post('structural-positions/{structural_position}/toggle', [StructuralPositionController::class, 'toggle'])->name('structural-positions.toggle');
        Route::get('master-penugasan', [StructuralPositionController::class, 'index'])->name('duty-positions.index');
        Route::resource('stakeholders', SchoolStakeholderController::class)->except(['create', 'show', 'edit']);
        Route::resource('activity-categories', ActivityCategoryController::class)->except(['create', 'show', 'edit']);

        // Penugasan Guru (Wali Kelas, Mapel Diampu, Supervisor & Struktural)
        Route::get('penugasan-guru', [TeacherAssignmentController::class, 'index'])->name('teacher-assignments.index');
        Route::post('penugasan-guru/mapel', [TeacherAssignmentController::class, 'storeSubjectAssignment'])->name('teacher-assignments.store-subject');
        Route::delete('penugasan-guru/mapel/{assignment}', [TeacherAssignmentController::class, 'destroySubjectAssignment'])->name('teacher-assignments.destroy-subject');
        Route::get('penugasan-guru/walikelas/template', [TeacherAssignmentController::class, 'downloadHomeroomTemplate'])->name('teacher-assignments.template-homeroom');
        Route::post('penugasan-guru/walikelas/import', [TeacherAssignmentController::class, 'importHomeroom'])->name('teacher-assignments.import-homeroom');
        Route::post('penugasan-guru/walikelas/{classroom}', [TeacherAssignmentController::class, 'updateHomeroom'])->name('teacher-assignments.update-homeroom');
        Route::get('penugasan-guru/supervisor/template', [TeacherAssignmentController::class, 'downloadSupervisorTemplate'])->name('teacher-assignments.template-supervisor');
        Route::post('penugasan-guru/supervisor/import', [TeacherAssignmentController::class, 'importSupervisor'])->name('teacher-assignments.import-supervisor');
        Route::post('penugasan-guru/supervisor', [TeacherAssignmentController::class, 'assignSupervisor'])->name('teacher-assignments.assign-supervisor');
        Route::delete('penugasan-guru/supervisor/{user}', [TeacherAssignmentController::class, 'removeSupervisor'])->name('teacher-assignments.remove-supervisor');
        Route::get('penugasan-guru/struktural/template', [TeacherAssignmentController::class, 'downloadStructuralTemplate'])->name('teacher-assignments.template-structural');
        Route::post('penugasan-guru/struktural/import', [TeacherAssignmentController::class, 'importStructural'])->name('teacher-assignments.import-structural');
        Route::post('penugasan-guru/struktural', [TeacherAssignmentController::class, 'storeStructuralAssignment'])->name('teacher-assignments.store-structural');
        Route::post('penugasan-guru/struktural/{assignment}/deactivate', [TeacherAssignmentController::class, 'deactivateStructuralAssignment'])->name('teacher-assignments.deactivate-structural');
        Route::delete('penugasan-guru/struktural/{assignment}', [TeacherAssignmentController::class, 'destroyStructuralAssignment'])->name('teacher-assignments.destroy-structural');
        Route::post('penugasan-guru/bk', [TeacherAssignmentController::class, 'storeBkAssignment'])->name('teacher-assignments.store-bk');
        Route::post('penugasan-guru/bk/sync', [TeacherAssignmentController::class, 'syncBkFromSubjects'])->name('teacher-assignments.sync-bk');
        Route::delete('penugasan-guru/bk/{assignment}', [TeacherAssignmentController::class, 'destroyBkAssignment'])->name('teacher-assignments.destroy-bk');
        Route::post('penugasan-guru/operasional', [TeacherAssignmentController::class, 'storeOperationalAssignment'])->name('teacher-assignments.store-operational');
        Route::post('penugasan-guru/operasional/{assignment}/deactivate', [TeacherAssignmentController::class, 'deactivateOperationalAssignment'])->name('teacher-assignments.deactivate-operational');
        Route::delete('penugasan-guru/operasional/{assignment}', [TeacherAssignmentController::class, 'destroyOperationalAssignment'])->name('teacher-assignments.destroy-operational');
        Route::get('penugasan-gtk', [TeacherAssignmentController::class, 'index'])->name('gtk-assignments.index');

        // Arsip Dokumen & Draf SK GTK
        Route::get('arsip-sk-gtk', [GtkDecreeController::class, 'index'])->name('gtk-decrees.index');
        Route::get('arsip-sk-gtk/tambah', [GtkDecreeController::class, 'create'])->name('gtk-decrees.create');
        Route::post('arsip-sk-gtk', [GtkDecreeController::class, 'store'])->name('gtk-decrees.store');
        Route::get('arsip-sk-gtk/{gtkDecree}', [GtkDecreeController::class, 'show'])->name('gtk-decrees.show');
        Route::get('arsip-sk-gtk/{gtkDecree}/edit', [GtkDecreeController::class, 'edit'])->name('gtk-decrees.edit');
        Route::put('arsip-sk-gtk/{gtkDecree}', [GtkDecreeController::class, 'update'])->name('gtk-decrees.update');
        Route::delete('arsip-sk-gtk/{gtkDecree}', [GtkDecreeController::class, 'destroy'])->name('gtk-decrees.destroy');
        Route::get('arsip-sk-gtk/{gtkDecree}/dokumen', [GtkDecreeController::class, 'downloadDocument'])->name('gtk-decrees.document');
        Route::get('arsip-sk-gtk/{gtkDecree}/pratinjau', [GtkDecreeController::class, 'previewImpact'])->name('gtk-decrees.preview');
        Route::get('arsip-sk-gtk/{gtkDecree}/pratinjau-pembatalan', [GtkDecreeController::class, 'previewCancellation'])->name('gtk-decrees.cancel-preview');
        Route::post('arsip-sk-gtk/{gtkDecree}/batalkan', [GtkDecreeController::class, 'cancel'])->name('gtk-decrees.cancel');
        Route::post('arsip-sk-gtk/{gtkDecree}/aktifkan', [GtkDecreeController::class, 'activate'])->name('gtk-decrees.activate');

        // Pengelolaan Penerima & Butir Rincian Keputusan SK GTK
        Route::post('arsip-sk-gtk/{gtkDecree}/penerima', [GtkDecreeController::class, 'addRecipients'])->name('gtk-decrees.recipients.store');
        Route::delete('arsip-sk-gtk/{gtkDecree}/penerima/{recipient}', [GtkDecreeController::class, 'removeRecipient'])->name('gtk-decrees.recipients.destroy');
        Route::post('arsip-sk-gtk/{gtkDecree}/rincian', [GtkDecreeController::class, 'storeDetail'])->name('gtk-decrees.details.store');
        Route::post('arsip-sk-gtk/{gtkDecree}/rincian/massal', [GtkDecreeController::class, 'storeBulkDetails'])->name('gtk-decrees.details.bulk-store');
        Route::delete('arsip-sk-gtk/{gtkDecree}/rincian/{detail}', [GtkDecreeController::class, 'destroyDetail'])->name('gtk-decrees.details.destroy');

        // Penugasan Pegawai Terpusat (Lintas Modul & Seluruh Sekolah)
        Route::get('penugasan-pegawai', [EmployeeAssignmentController::class, 'index'])->name('employee-assignments.index');
        Route::post('penugasan-pegawai', [EmployeeAssignmentController::class, 'store'])->name('employee-assignments.store');
        Route::post('penugasan-pegawai/bulk-close', [EmployeeAssignmentController::class, 'bulkClose'])->name('employee-assignments.bulk-close');
        Route::post('penugasan-pegawai/rollover', [EmployeeAssignmentController::class, 'rollover'])->name('employee-assignments.rollover');
        Route::put('penugasan-pegawai/{assignment}', [EmployeeAssignmentController::class, 'update'])->name('employee-assignments.update');
        Route::post('penugasan-pegawai/{assignment}/close', [EmployeeAssignmentController::class, 'close'])->name('employee-assignments.close');
        Route::post('penugasan-pegawai/{assignment}/toggle', [EmployeeAssignmentController::class, 'toggle'])->name('employee-assignments.toggle');
        Route::delete('penugasan-pegawai/{assignment}', [EmployeeAssignmentController::class, 'destroy'])->name('employee-assignments.destroy');

        // Pengelolaan Data Siswa (Create, Import, Dapodik, Update, Delete)
        Route::post('siswa', [StudentController::class, 'store'])->name('students.store');
        Route::post('siswa/import', [StudentController::class, 'import'])->name('students.import');
        Route::post('siswa/import-dapodik', [StudentController::class, 'importDapodik'])->name('students.import-dapodik');
        Route::post('siswa/mutasi', [StudentController::class, 'recordMutation'])->name('students.mutations.store');
        Route::delete('siswa/mutasi/{mutation}', [StudentController::class, 'destroyMutation'])->name('students.mutations.destroy');
        Route::get('siswa/mutasi/download/{mutation}', [StudentController::class, 'downloadMutationAttachment'])->name('students.mutations.download');
        Route::get('siswa/{student}/edit-data', [StudentController::class, 'editData'])->name('students.edit-data')->whereNumber('student');
        Route::put('siswa/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('siswa/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    });

    // Kurikulum: Kegiatan Sekolah, Jadwal Pelajaran & Kalender
    Route::prefix('kurikulum')->name('kurikulum.')->group(function () {
        // Kegiatan Sekolah
        Route::get('kegiatan-sekolah', [SchoolActivityController::class, 'index'])->name('activities.index');
        Route::post('kegiatan-sekolah', [SchoolActivityController::class, 'store'])->name('activities.store');
        Route::put('kegiatan-sekolah/{schoolActivity}', [SchoolActivityController::class, 'update'])->name('activities.update');
        Route::delete('kegiatan-sekolah/{schoolActivity}', [SchoolActivityController::class, 'destroy'])->name('activities.destroy');
        Route::post('kegiatan-sekolah/{schoolActivity}/jadwalkan', [SchoolActivityController::class, 'quickSchedule'])->name('activities.quick-schedule');

        Route::get('jadwal-pelajaran', [LessonScheduleController::class, 'index'])->name('schedules.index');
        Route::get('jadwal-pelajaran/print', [LessonScheduleController::class, 'print'])->name('schedules.print');
        Route::post('jadwal-pelajaran', [LessonScheduleController::class, 'store'])->name('schedules.store');
        Route::put('jadwal-pelajaran/{schedule}', [LessonScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('jadwal-pelajaran/{schedule}', [LessonScheduleController::class, 'destroy'])->name('schedules.destroy');
        Route::post('jadwal-pelajaran/upload', [LessonScheduleController::class, 'upload'])->name('schedules.upload');

        Route::middleware('role:waka_kurikulum,kepala_sekolah,operator_kurikulum')->group(function () {
            Route::get('arsip-jadwal', [TimetableUploadController::class, 'index'])->name('timetables.index');
            Route::post('arsip-jadwal', [TimetableUploadController::class, 'store'])->name('timetables.store');
            Route::get('arsip-jadwal/{upload}/download', [TimetableUploadController::class, 'download'])->name('timetables.download');
            Route::get('arsip-jadwal/{upload}/preview', [TimetableUploadController::class, 'preview'])->name('timetables.preview');
            Route::post('arsip-jadwal/{upload}/reapply', [TimetableUploadController::class, 'reapply'])->name('timetables.reapply');
            Route::delete('arsip-jadwal/{upload}', [TimetableUploadController::class, 'destroy'])->name('timetables.destroy');

            // Hasil Terjemahan Jadwal (Staging & Koreksi)
            Route::get('arsip-jadwal/{upload}/terjemahan', [TimetableUploadController::class, 'translation'])->name('timetables.translation');
            Route::post('arsip-jadwal/{upload}/terjemahan/item', [TimetableUploadController::class, 'storeItem'])->name('timetables.translation.store');
            Route::put('arsip-jadwal/{upload}/terjemahan/{item}', [TimetableUploadController::class, 'updateItem'])->name('timetables.translation.update');
            Route::delete('arsip-jadwal/{upload}/terjemahan/{item}', [TimetableUploadController::class, 'destroyItem'])->name('timetables.translation.destroy');
            Route::post('arsip-jadwal/{upload}/terjemahan/reparse', [TimetableUploadController::class, 'reparse'])->name('timetables.translation.reparse');
            Route::post('arsip-jadwal/{upload}/terjemahan/apply', [TimetableUploadController::class, 'applyTranslation'])->name('timetables.translation.apply');
        });

        // Kalender Kegiatan Kurikulum & Kaldik
        Route::get('kalender-kegiatan', [CurriculumCalendarController::class, 'index'])->name('calendar.index');
        Route::get('kalender-kegiatan/events', [CurriculumCalendarController::class, 'events'])->name('calendar.events');
        Route::post('kalender-kegiatan', [CurriculumCalendarController::class, 'store'])->name('calendar.store');
        Route::put('kalender-kegiatan/{event}', [CurriculumCalendarController::class, 'update'])->name('calendar.update');
        Route::delete('kalender-kegiatan/{event}', [CurriculumCalendarController::class, 'destroy'])->name('calendar.destroy');
        Route::post('kalender-kegiatan/upload-kaldik', [CurriculumCalendarController::class, 'upload'])->name('calendar.upload');
        Route::get('kalender-kegiatan/kaldik/{upload}/download', [CurriculumCalendarController::class, 'downloadKaldik'])->name('calendar.kaldik.download');
        Route::get('kalender-kegiatan/kaldik/{upload}/preview', [CurriculumCalendarController::class, 'previewKaldik'])->name('calendar.kaldik.preview');

        // Integrasi GarudaCBT (Sinkronisasi Dua Arah)
        Route::middleware('role:super_admin,waka_kurikulum,kepala_sekolah,operator_kurikulum')->group(function () {
            Route::get('cbt-sync', [CbtIntegrationController::class, 'index'])->name('cbt.index');
            Route::post('cbt-sync', [CbtIntegrationController::class, 'sync'])->name('cbt.sync');
            Route::get('cbt-sync/test-connection', [CbtIntegrationController::class, 'testConnection'])->name('cbt.test-connection');
        });
    });

    // Backward-compatible alias for legacy school-activities URL
    Route::redirect('school-activities', '/kurikulum/kegiatan-sekolah')->name('school-activities.index');

    // Schedules Management
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::middleware('permission:manage_schedules')->group(function () {
        Route::get('schedules/create', [ScheduleController::class, 'create'])->name('schedules.create');
        Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->name('schedules.edit');
        Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    });

    // Menu Tahapan Supervisi Langsung (Sesuai Menu Prototipe)
    Route::get('/praobservasi', [SupervisionConductController::class, 'indexPreobs'])->name('supervisions.preobs');
    Route::get('/observasi', [SupervisionConductController::class, 'indexObs'])->name('supervisions.obs');
    Route::get('/dokumentasi', [SupervisionConductController::class, 'indexDocs'])->name('supervisions.docs');
    Route::get('/hasil-supervisi', [SupervisionConductController::class, 'indexResults'])->name('supervisions.results');

    // Menu Cetak Laporan & Riwayat Supervisi (Sesuai Prototipe)
    Route::get('/cetak-laporan', [ReportController::class, 'indexPrint'])->name('reports.print-index');
    Route::get('/riwayat', [ReportController::class, 'history'])->name('reports.history');

    // Dashboard Khusus Supervisi Guru
    Route::get('/supervisi/dashboard', [SupervisionDashboardController::class, 'index'])->name('supervisions.dashboard');

    // Supervision Conduct & Workflow
    Route::prefix('supervisi')->name('supervisions.')->group(function () {
        Route::get('/{supervision}', [SupervisionConductController::class, 'show'])->name('show');
        Route::post('/{supervision}/upload-rpp', [SupervisionConductController::class, 'uploadRpp'])->name('upload-rpp');
        Route::get('/{supervision}/unduh-rpp', [SupervisionConductController::class, 'downloadRpp'])->name('download-rpp');
        Route::get('/foto/{photo}', [SupervisionConductController::class, 'viewPhoto'])->name('view-photo');

        // Tahap 1: Praobservasi
        Route::get('/{supervision}/praobservasi', [SupervisionConductController::class, 'preObservation'])->name('pre-observation');
        Route::post('/{supervision}/praobservasi', [SupervisionConductController::class, 'savePreObservation'])->name('save-pre-observation');

        // Tahap 2: Observasi
        Route::get('/{supervision}/observasi', [SupervisionConductController::class, 'observation'])->name('observation');
        Route::post('/{supervision}/observasi', [SupervisionConductController::class, 'saveObservation'])->name('save-observation');

        // Tahap 3: Pascaobservasi
        Route::get('/{supervision}/pascaobservasi', [SupervisionConductController::class, 'postObservation'])->name('post-observation');
        Route::post('/{supervision}/pascaobservasi', [SupervisionConductController::class, 'savePostObservation'])->name('save-post-observation');

        // Tahap 4: Dokumentasi
        Route::get('/{supervision}/dokumentasi', [SupervisionConductController::class, 'documentation'])->name('documentation');
        Route::post('/{supervision}/dokumentasi', [SupervisionConductController::class, 'uploadPhoto'])->name('upload-photo');

        // Finalisasi
        Route::post('/{supervision}/finalisasi', [SupervisionConductController::class, 'finalize'])->name('finalize');
    });

    Route::delete('/dokumentasi/{photo}', [SupervisionConductController::class, 'deletePhoto'])->name('supervisions.delete-photo');

    // Persetujuan Kepala Sekolah (Approval)
    Route::middleware('permission:approve_supervision')->prefix('persetujuan')->name('approvals.')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');
        Route::get('/{supervision}', [ApprovalController::class, 'show'])->name('show');
        Route::post('/{supervision}/setujui', [ApprovalController::class, 'approve'])->name('approve');
        Route::post('/{supervision}/tolak', [ApprovalController::class, 'reject'])->name('reject');
    });

    // Laporan & Rekapitulasi
    Route::prefix('laporan')->name('reports.')->group(function () {
        Route::get('/{supervision}/cetak', [ReportController::class, 'print'])->name('print');
        Route::get('/{supervision}/pdf', [ReportController::class, 'pdf'])->name('pdf');
        Route::middleware('permission:view_all_supervision')->group(function () {
            Route::get('/rekapitulasi', [ReportController::class, 'recap'])->name('recap');
            Route::get('/rekapitulasi/ekspor', [ReportController::class, 'exportExcel'])->name('export-excel');
        });
    });

    // Kustomisasi Indikator & Rubrik Supervisi (Waka / Admin)
    Route::middleware('permission:manage_rubrics')->prefix('rubrik')->name('rubrics.')->group(function () {
        Route::get('/', [RubricController::class, 'index'])->name('index');
        Route::post('/fase', [RubricController::class, 'storePhase'])->name('store-phase');
        Route::put('/fase/{phase}', [RubricController::class, 'updatePhase'])->name('update-phase');
        Route::delete('/fase/{phase}', [RubricController::class, 'deletePhase'])->name('delete-phase');
        Route::post('/indikator', [RubricController::class, 'storeIndicator'])->name('store-indicator');
        Route::put('/indikator/{indicator}', [RubricController::class, 'updateIndicator'])->name('update-indicator');
        Route::delete('/indikator/{indicator}', [RubricController::class, 'deleteIndicator'])->name('delete-indicator');
        Route::post('/ambang-predikat', [RubricController::class, 'updatePredicates'])->name('update-predicates');
    });

    // Menu Role & Setting Hak Akses Pengguna (Super Admin / Waka)
    Route::middleware('permission:manage_roles')->prefix('pengaturan/hak-akses')->name('roles.')->group(function () {
        Route::get('/', [RolePermissionController::class, 'index'])->name('index');
        Route::post('/', [RolePermissionController::class, 'update'])->name('update');
        Route::post('/peran', [RolePermissionController::class, 'storeRole'])->name('store-role');
    });

    // Profil Sekolah (Dapat diakses & dilihat oleh semua pengguna terdaftar)
    Route::get('profil-sekolah', [SettingController::class, 'index'])->name('settings.index');
    Route::post('profil-sekolah', [SettingController::class, 'update'])->name('settings.update')->middleware('permission:manage_settings');
    Route::get('pengaturan/sekolah', fn () => redirect()->route('settings.index'));

    // Pengaturan Bot Telegram & Notifikasi Terpusat Sekolah (Pengaturan)
    Route::middleware('permission:manage_settings')->prefix('pengaturan/telegram')->group(function () {
        Route::get('/', [TelegramBotController::class, 'index'])->name('telegram.index');
        Route::post('/pengaturan', [TelegramBotController::class, 'updateSettings'])->name('telegram.settings');
        Route::post('/guru-chat-id', [TelegramBotController::class, 'updateTeacherChatId'])->name('telegram.teacher-chat-id');
        Route::post('/test', [TelegramBotController::class, 'testSend'])->name('telegram.test');
        Route::post('/broadcast', [TelegramBotController::class, 'sendBroadcast'])->name('telegram.broadcast');
        Route::post('/kirim-pengingat', [TelegramBotController::class, 'sendReminder'])->name('telegram.reminder');
        Route::post('/kirim-pengingat-supervisi', [TelegramBotController::class, 'sendSupervisionReminder'])->name('telegram.supervision-reminder');
        Route::post('/set-webhook', [TelegramBotController::class, 'setWebhook'])->name('telegram.set-webhook');
        Route::post('/delete-webhook', [TelegramBotController::class, 'deleteWebhook'])->name('telegram.delete-webhook');

        // Saluran Grup Dinamis (Kelompok, Menu, dan Undangan)
        Route::post('/saluran-grup', [TelegramBotController::class, 'storeChannel'])->name('telegram.channel.store');
        Route::put('/saluran-grup/{channel}', [TelegramBotController::class, 'updateChannel'])->name('telegram.channel.update');
        Route::delete('/saluran-grup/{channel}', [TelegramBotController::class, 'destroyChannel'])->name('telegram.channel.destroy');
        Route::post('/saluran-grup/{channel}/sync-commands', [TelegramBotController::class, 'syncChannelCommands'])->name('telegram.channel.sync-commands');
        Route::post('/saluran-grup/{channel}/invite', [TelegramBotController::class, 'inviteChannelMembers'])->name('telegram.channel.invite');
    });

    // Catatan & Masukan Perbaikan Aplikasi (Terbuka untuk semua pengguna login)
    Route::prefix('catatan-perbaikan')->name('feedbacks.')->group(function () {
        Route::get('/', [AppFeedbackController::class, 'index'])->name('index');
        Route::post('/', [AppFeedbackController::class, 'store'])->name('store');
        Route::get('/{feedback}/lampiran', [AppFeedbackController::class, 'downloadAttachment'])->name('attachment');
        Route::put('/{feedback}/tanggapan', [AppFeedbackController::class, 'updateResponse'])->name('response');
        Route::delete('/{feedback}', [AppFeedbackController::class, 'destroy'])->name('destroy');
    });

    // Notepad / Catatan Pribadi & Pengingat (Menu di Pengaturan)
    Route::prefix('pengaturan/notepad')->name('notepads.')->group(function () {
        Route::get('/', [NotepadController::class, 'index'])->name('index');
        Route::post('/', [NotepadController::class, 'store'])->name('store');
        Route::put('/{notepad}', [NotepadController::class, 'update'])->name('update');
        Route::post('/{notepad}/pin', [NotepadController::class, 'togglePin'])->name('pin');
        Route::delete('/{notepad}', [NotepadController::class, 'destroy'])->name('destroy');
    });

    // ==========================================
    // MODUL PIKET (GURU PIKET & KEDISIPLINAN)
    // ==========================================
    Route::middleware('permission:access_piket')->prefix('piket')->name('piket.')->group(function () {
        // 1. Dashboard Piket
        Route::get('/', [PiketDashboardController::class, 'index'])->name('dashboard');

        // 2. Jadwal & Petugas Piket
        Route::get('jadwal', [PiketScheduleController::class, 'index'])->name('schedules.index');
        Route::middleware('permission:manage_piket_schedules')->group(function () {
            Route::post('jadwal', [PiketScheduleController::class, 'store'])->name('schedules.store');
            Route::put('jadwal/{schedule}', [PiketScheduleController::class, 'update'])->name('schedules.update');
            Route::delete('jadwal/{schedule}', [PiketScheduleController::class, 'destroy'])->name('schedules.destroy');
        });

        // 3. Tukar Jadwal Piket
        Route::middleware('permission:manage_piket_schedules')->group(function () {
            Route::get('tukar-jadwal', [PiketDutySwapController::class, 'index'])->name('swaps.index');
            Route::post('tukar-jadwal', [PiketDutySwapController::class, 'store'])->name('swaps.store');
            Route::post('tukar-jadwal/{swap}/setujui', [PiketDutySwapController::class, 'approve'])->name('swaps.approve');
            Route::post('tukar-jadwal/{swap}/tolak', [PiketDutySwapController::class, 'reject'])->name('swaps.reject');
        });

        // 4. Kalender Piket
        Route::middleware('permission:manage_piket_schedules')->group(function () {
            Route::get('kalender', [PiketCalendarController::class, 'index'])->name('calendar.index');
        });

        // 5. Jurnal Piket Harian
        Route::middleware('permission:operate_piket')->group(function () {
            Route::get('jurnal', [PiketJournalController::class, 'index'])->name('journals.index');
            Route::get('jurnal/hari-ini', [PiketJournalController::class, 'today'])->name('journals.today');
            Route::get('jurnal/{journal}', [PiketJournalController::class, 'show'])->name('journals.show');
            Route::post('jurnal/{journal}/presensi', [PiketJournalController::class, 'updateAttendance'])->name('journals.update-attendance');
            Route::post('jurnal/{journal}/sinkron-mesin', [PiketJournalController::class, 'syncMachineAttendance'])->name('journals.sync-machine');
            Route::post('jurnal/{journal}/catatan', [PiketJournalController::class, 'updateNotes'])->name('journals.update-notes');
            Route::post('jurnal/{journal}/selesai', [PiketJournalController::class, 'complete'])->name('journals.complete');
            Route::get('jurnal/{journal}/cetak', [PiketJournalController::class, 'print'])->name('journals.print');
        });

        // 6. Perizinan Siswa
        Route::get('perizinan/{permit}/lampiran', [PiketPermitController::class, 'downloadAttachment'])->name('permits.attachment');
        Route::middleware('permission:operate_piket')->group(function () {
            Route::get('perizinan', [PiketPermitController::class, 'index'])->name('permits.index');
            Route::get('perizinan/buat', [PiketPermitController::class, 'create'])->name('permits.create');
            Route::post('perizinan', [PiketPermitController::class, 'store'])->name('permits.store');
            Route::get('perizinan/{permit}', [PiketPermitController::class, 'show'])->name('permits.show');
            Route::get('perizinan/{permit}/slip', [PiketPermitController::class, 'printSlip'])->name('permits.slip');
            Route::delete('perizinan/{permit}', [PiketPermitController::class, 'destroy'])->name('permits.destroy');
        });

        // 7. Guru Inval & Kelas Kosong
        Route::get('inval/{inval}/tugas', [PiketInvalController::class, 'downloadAssignment'])->name('invals.assignment');
        Route::middleware('permission:operate_piket')->group(function () {
            Route::get('inval', [PiketInvalController::class, 'index'])->name('inval.index');
            Route::post('inval', [PiketInvalController::class, 'store'])->name('inval.store');
            Route::put('inval/{inval}', [PiketInvalController::class, 'update'])->name('inval.update');
            Route::delete('inval/{inval}', [PiketInvalController::class, 'destroy'])->name('inval.destroy');
        });

        // 8. Catatan Kejadian / Kasus & Dashboard Kedisiplinan
        Route::get('kedisiplinan/dashboard', [DisciplineDashboardController::class, 'index'])->name('discipline.dashboard');
        Route::get('kedisiplinan', [DisciplineDashboardController::class, 'index'])->name('discipline.index');
        Route::get('kasus', [PiketIncidentController::class, 'index'])->name('incidents.index');
        Route::get('kasus/{incident}/foto', [PiketIncidentController::class, 'viewPhoto'])->name('incidents.photo');
        Route::post('kasus/{incident}/verifikasi', [PiketIncidentController::class, 'verify'])->name('incidents.verify');
        Route::get('kasus/{incident}/surat-panggilan', [PiketIncidentController::class, 'exportSuratPanggilan'])->name('incidents.surat_panggilan');
        Route::middleware('permission:manage_piket_cases')->group(function () {
            Route::get('kasus/catat', [PiketIncidentController::class, 'create'])->name('incidents.create');
            Route::post('kasus', [PiketIncidentController::class, 'store'])->name('incidents.store');
            Route::post('kasus/{incident}/status', [PiketIncidentController::class, 'updateStatus'])->name('incidents.status');
            Route::delete('kasus/{incident}', [PiketIncidentController::class, 'destroy'])->name('incidents.destroy');
        });

        // 9. Buku Tamu
        Route::middleware('permission:operate_piket')->group(function () {
            Route::get('tamu', [PiketGuestController::class, 'index'])->name('guests.index');
            Route::post('tamu', [PiketGuestController::class, 'store'])->name('guests.store');
            Route::post('tamu/{guest}/checkout', [PiketGuestController::class, 'checkOut'])->name('guests.checkout');
            Route::delete('tamu/{guest}', [PiketGuestController::class, 'destroy'])->name('guests.destroy');
        });

        // 10. Rekapitulasi & Laporan
        Route::middleware('permission:view_piket_reports')->group(function () {
            Route::get('laporan', [PiketReportController::class, 'index'])->name('reports.index');
            Route::get('laporan/cetak', [PiketReportController::class, 'print'])->name('reports.print');
            Route::get('laporan/ekspor', [PiketReportController::class, 'export'])->name('reports.export');
        });

        // 11. Data Siswa
        Route::get('siswa/search', [PiketStudentController::class, 'search'])->name('students.search');
        Route::middleware('permission:operate_piket')->group(function () {
            Route::get('siswa', [PiketStudentController::class, 'index'])->name('students.index');
            Route::post('siswa', [PiketStudentController::class, 'store'])->name('students.store');
            Route::put('siswa/{student}', [PiketStudentController::class, 'update'])->name('students.update');
            Route::delete('siswa/{student}', [PiketStudentController::class, 'destroy'])->name('students.destroy');
            Route::get('siswa/template-import', [PiketStudentController::class, 'downloadTemplate'])->name('students.template');
            Route::post('siswa/import', [PiketStudentController::class, 'import'])->name('students.import');
        });

        // 12. Bot Telegram Terpusat (Menggunakan TelegramBotController)
        Route::get('telegram', [TelegramBotController::class, 'index'])->name('telegram.index');
    });

    // ==========================================
    // Modul Operasional Bimbingan & Konseling (BK)
    // ==========================================
    Route::prefix('bk')->name('bk.')->group(function () {
        // 1. Dashboard BK & Rujukan Kasus Meja Piket
        Route::get('/', [BkDashboardController::class, 'index'])->name('index');
        Route::get('dashboard', [BkDashboardController::class, 'index'])->name('dashboard');
        Route::get('rujukan', [BkReferralController::class, 'index'])->name('referrals.index');
        Route::get('rujukan/{incident}', [BkReferralController::class, 'show'])->name('referrals.show');
        Route::post('rujukan/{incident}/status', [BkReferralController::class, 'updateStatus'])->name('referrals.status');

        // 1.1 Kelompok Binaan BK (Career & Counseling Cohorts)
        Route::get('kelompok', [BkGroupController::class, 'index'])->name('groups.index');
        Route::get('kelompok/buat', [BkGroupController::class, 'create'])->name('groups.create');
        Route::post('kelompok', [BkGroupController::class, 'store'])->name('groups.store');
        Route::get('kelompok/{group}', [BkGroupController::class, 'show'])->name('groups.show');
        Route::post('kelompok/{group}/tambah-siswa', [BkGroupController::class, 'addStudents'])->name('groups.add-students');
        Route::delete('kelompok/{group}/siswa/{student}', [BkGroupController::class, 'removeStudent'])->name('groups.remove-student');
        Route::delete('kelompok/{group}', [BkGroupController::class, 'destroy'])->name('groups.destroy');

        // 1.2 Dashboard Analisis Karir & Penyaluran Kerja BKK
        Route::get('analisis-karir', [BkCareerAnalyticsController::class, 'index'])->name('career.analytics');

        // 2. Pencatatan & Pengelolaan Layanan BK
        Route::get('layanan', [BkServiceController::class, 'index'])->name('services.index');
        Route::get('layanan/buat', [BkServiceController::class, 'create'])->name('services.create');
        Route::post('layanan', [BkServiceController::class, 'store'])->name('services.store');
        Route::get('layanan/{service}', [BkServiceController::class, 'show'])->name('services.show');
        Route::get('layanan/{service}/edit', [BkServiceController::class, 'edit'])->name('services.edit');
        Route::put('layanan/{service}', [BkServiceController::class, 'update'])->name('services.update');
        Route::post('layanan/{service}/rtl', [BkServiceController::class, 'updateRtl'])->name('services.rtl');
        Route::delete('layanan/{service}', [BkServiceController::class, 'destroy'])->name('services.destroy');

        // 3. Buku Pribadi Siswa Digital
        Route::get('buku-pribadi', [BkStudentHistoryController::class, 'index'])->name('students.index');
        Route::get('siswa/{student}/riwayat', [BkStudentHistoryController::class, 'show'])->name('students.history');
        Route::get('siswa/{student}/riwayat/pdf', [BkStudentHistoryController::class, 'exportPdf'])->name('students.history.pdf');

        // 4. Tab 3: Minat, Bakat, dan Rencana Karier Siswa
        Route::post('siswa/{student}/minat-karier', [BkCareerProfileController::class, 'storeOrUpdate'])->name('students.career.store');
        Route::post('siswa/{student}/asesmen', [BkCareerProfileController::class, 'storeAssessment'])->name('students.assessments.store');
        Route::delete('siswa/{student}/asesmen/{assessment}', [BkCareerProfileController::class, 'destroyAssessment'])->name('students.assessments.destroy');

        // 5. Tab 4: Prestasi, Penghargaan & Organisasi Siswa
        Route::post('siswa/{student}/prestasi', [BkStudentRecordController::class, 'storeAchievement'])->name('students.achievements.store');
        Route::delete('siswa/{student}/prestasi/{achievement}', [BkStudentRecordController::class, 'destroyAchievement'])->name('students.achievements.destroy');

        // 6. Tab 5: Catatan Observasi Perkembangan Siswa Non-Kasus
        Route::post('siswa/{student}/catatan-perkembangan', [BkStudentRecordController::class, 'storeDevelopmentNote'])->name('students.development-notes.store');
        Route::delete('siswa/{student}/catatan-perkembangan/{note}', [BkStudentRecordController::class, 'destroyDevelopmentNote'])->name('students.development-notes.destroy');

        // 7. Tab 8: Dokumen Pendukung & Arsip Digital BK
        Route::post('siswa/{student}/dokumen', [BkStudentRecordController::class, 'storeDocument'])->name('students.documents.store');
        Route::get('siswa/{student}/dokumen/{document}/download', [BkStudentRecordController::class, 'downloadDocument'])->name('students.documents.download');
        Route::delete('siswa/{student}/dokumen/{document}', [BkStudentRecordController::class, 'destroyDocument'])->name('students.documents.destroy');
    });

    // ==========================================
    // MODUL VISITASI & PEMANTAUAN SISWA
    // Multi-Petugas: Guru BK, Wali Kelas, Tim Kedisiplinan / Kesiswaan
    // ==========================================
    Route::prefix('visitasi')->name('visitations.')->group(function () {
        Route::get('/', [StudentVisitationController::class, 'index'])->name('index');
        Route::get('/tambah', [StudentVisitationController::class, 'create'])->name('create');
        Route::post('/', [StudentVisitationController::class, 'store'])->name('store');
        Route::get('/{visitation}', [StudentVisitationController::class, 'show'])->name('show');
        Route::get('/{visitation}/edit', [StudentVisitationController::class, 'edit'])->name('edit');
        Route::put('/{visitation}', [StudentVisitationController::class, 'update'])->name('update');
        Route::get('/{visitation}/lampiran', [StudentVisitationController::class, 'downloadAttachment'])->name('attachment');
        Route::get('/{visitation}/pdf', [StudentVisitationController::class, 'exportPdf'])->name('pdf');
        Route::delete('/{visitation}', [StudentVisitationController::class, 'destroy'])->name('destroy');
    });

    // ==========================================
    // MODUL TIM KEDISIPLINAN
    // ==========================================
    Route::prefix('kedisiplinan')->name('discipline.')->group(function () {
        Route::get('/', [DisciplineDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [DisciplineDashboardController::class, 'index']);
    });

    // Menu Khusus Super Admin: Manajemen Pengguna, User Aktif & Log Aktivitas User
    Route::middleware('role:super_admin')->prefix('superadmin')->group(function () {
        // Manajemen Akun Pengguna (Multi-Super Admin, Guru, Staf)
        Route::get('pengguna', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('pengguna', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('pengguna/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::post('pengguna/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('pengguna/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::delete('pengguna/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        // Penugasan Role Kontekstual (Fase 2)
        Route::post('pengguna/{user}/penugasan', [UserManagementController::class, 'storeAssignment'])->name('users.assignments.store');
        Route::delete('pengguna/penugasan/{assignment}', [UserManagementController::class, 'destroyAssignment'])->name('users.assignments.destroy');
        Route::post('pengguna/penugasan/{assignment}/toggle', [UserManagementController::class, 'toggleAssignmentStatus'])->name('users.assignments.toggle');

        // User Aktif & Monitoring Sesi
        Route::get('user-aktif', [ActiveUserController::class, 'index'])->name('active-users.index');
        Route::delete('user-aktif/sesi/{sessionId}', [ActiveUserController::class, 'destroySession'])->name('active-users.destroy-session');
        Route::post('user-aktif/toggle-status/{user}', [ActiveUserController::class, 'toggleStatus'])->name('active-users.toggle-status');

        // Log Aktivitas User
        Route::get('log-aktivitas', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::delete('log-aktivitas/hapus-lama', [ActivityLogController::class, 'clear'])->name('activity-logs.clear');
        Route::delete('log-aktivitas/{log}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
    });

    // Presensi Mesin Absensi (Fingerspot / ADMS)
    Route::prefix('presensi')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::get('/api/terbaru', [AttendanceController::class, 'getLatestLogs'])->name('api.latest');
        // Halaman Laporan Presensi Terpisah
        Route::get('/laporan', [AttendanceController::class, 'report'])->name('report');
        Route::get('/laporan/cetak', [AttendanceController::class, 'printReport'])->name('report.print');
        Route::get('/laporan/guru-staf', [AttendanceController::class, 'reportTeachers'])->name('report.teachers');
        Route::get('/laporan/guru-staf/cetak', [AttendanceController::class, 'printReportTeachers'])->name('report.teachers.print');
        Route::get('/laporan/siswa', [AttendanceController::class, 'reportStudents'])->name('report.students');
        Route::get('/laporan/siswa/cetak', [AttendanceController::class, 'printReportStudents'])->name('report.students.print');

        // Presensi Mandiri GTK via Geotagging GPS (Mobile / PWA)
        Route::prefix('geotag')->name('geotag.')->group(function () {
            Route::get('/', [GeotagAttendanceController::class, 'index'])->name('index');
            Route::post('/simpan', [GeotagAttendanceController::class, 'store'])->name('store');
            Route::get('/selfie/{log}', [GeotagAttendanceController::class, 'viewSelfie'])->name('selfie');
        });

        // Presensi Siswa oleh Guru Mapel (Masuk Kelas & Tukar Jam)
        Route::prefix('mapel')->name('subject.')->group(function () {
            Route::get('/', [SubjectAttendanceController::class, 'index'])->name('index');
            Route::get('/isi', [SubjectAttendanceController::class, 'take'])->name('take');
            Route::post('/simpan', [SubjectAttendanceController::class, 'store'])->name('store');
            Route::get('/riwayat', [SubjectAttendanceController::class, 'history'])->name('history');
            Route::get('/sesi/{session}', [SubjectAttendanceController::class, 'show'])->name('show');
            Route::get('/jadwal-sekolah', [SubjectAttendanceController::class, 'schoolSchedule'])->name('school-schedule');
        });

        // Pelaporan Izin Siswa Perwalian oleh Wali Kelas
        Route::prefix('izin-siswa')->name('homeroom-permits.')->group(function () {
            Route::get('/', [HomeroomStudentPermitController::class, 'index'])->name('index');
            Route::get('/catat', [HomeroomStudentPermitController::class, 'create'])->name('create');
            Route::post('/simpan', [HomeroomStudentPermitController::class, 'store'])->name('store');
            Route::delete('/{permit}', [HomeroomStudentPermitController::class, 'destroy'])->name('destroy');
        });

        // Rekapitulasi Presensi Terpadu Siswa Perwalian (Wali Kelas)
        Route::get('/rekap-kelas', [HomeroomAttendanceRecapController::class, 'index'])->name('homeroom-recap.index');
        Route::get('/rekap-kelas/ekspor-excel', [HomeroomAttendanceRecapController::class, 'exportExcel'])->name('homeroom-recap.export-excel');
        Route::get('/rekap-kelas/unduh-pdf', [HomeroomAttendanceRecapController::class, 'downloadPdf'])->name('homeroom-recap.download-pdf');

        // Izin & Cuti GTK (Guru & Tenaga Kependidikan)
        Route::get('/izin-cuti-gtk/ajukan', [GtkPermitController::class, 'create'])->name('gtk-permits.create');
        Route::get('/izin-cuti-gtk', [GtkPermitController::class, 'index'])->name('gtk-permits.index');
        Route::post('/izin-cuti-gtk', [GtkPermitController::class, 'store'])->name('gtk-permits.store');
        Route::get('/izin-cuti-gtk/{gtkPermit}/dokumen', [GtkPermitController::class, 'downloadDocument'])->name('gtk-permits.document');
        Route::get('/izin-cuti-gtk/{gtkPermit}/tugas', [GtkPermitController::class, 'downloadAssignment'])->name('gtk-permits.assignment');
        Route::post('/izin-cuti-gtk/sinkron-piket', [GtkPermitController::class, 'syncPiket'])->name('gtk-permits.sync-piket');
        Route::post('/izin-cuti-gtk/{gtkPermit}/setujui', [GtkPermitController::class, 'approve'])->name('gtk-permits.approve');
        Route::post('/izin-cuti-gtk/{gtkPermit}/tolak', [GtkPermitController::class, 'reject'])->name('gtk-permits.reject');
        Route::put('/izin-cuti-gtk/{gtkPermit}', [GtkPermitController::class, 'update'])->name('gtk-permits.update');
        Route::delete('/izin-cuti-gtk/{gtkPermit}', [GtkPermitController::class, 'destroy'])->name('gtk-permits.destroy');

        // Presensi Susulan / Manual GTK (Kendala Mati Listrik / Force Majeure - Web Desktop Only)
        Route::prefix('gtk-susulan')->name('manual-gtk.')->group(function () {
            Route::get('/', [ManualGtkAttendanceController::class, 'index'])->name('index');
            Route::post('/simpan', [ManualGtkAttendanceController::class, 'store'])->name('store');
            Route::delete('/{log}', [ManualGtkAttendanceController::class, 'destroy'])->name('destroy');
        });

        // Pembersihan Log Aplikasi
        Route::post('/bersihkan-log', [AttendanceController::class, 'clearLogs'])->name('logs.clear');

        // Pengelolaan & Konfigurasi Mesin Absensi (Khusus Admin / Super Admin)
        Route::middleware('role:super_admin')->prefix('mesin')->name('devices.')->group(function () {
            Route::get('/', [DeviceManagementController::class, 'index'])->name('index');
            Route::get('/biometrik', [DeviceManagementController::class, 'biometrics'])->name('biometrics');
            Route::post('/biometrik/distribusikan', [DeviceManagementController::class, 'broadcastBiometrics'])->name('biometrics.broadcast');
            Route::delete('/biometrik/{biometric}', [DeviceManagementController::class, 'destroyBiometric'])->name('biometrics.destroy');
            Route::get('/cadangan-geisa/{file?}', [DeviceManagementController::class, 'downloadGeisaBackup'])->name('backup-geisa.download');
            Route::post('/', [DeviceManagementController::class, 'store'])->name('store');
            Route::put('/{device}', [DeviceManagementController::class, 'update'])->name('update');
            Route::post('/{device}/verifikasi', [DeviceManagementController::class, 'approve'])->name('approve');
            Route::delete('/{device}', [DeviceManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{device}/tarik', [DeviceManagementController::class, 'sync'])->name('sync');
            Route::post('/{device}/tes', [DeviceManagementController::class, 'testConnection'])->name('test');
            Route::post('/{device}/sinkron-waktu', [DeviceManagementController::class, 'syncTime'])->name('sync-time');
            Route::post('/sinkron-data-pengguna', [DeviceManagementController::class, 'syncUsers'])->name('sync-users');
            Route::get('/api/status-semua', [DeviceManagementController::class, 'apiAllStatus'])->name('api-all-status');

            // Remote Cockpit Per-Mesin
            Route::prefix('{device}')->name('manage.')->group(function () {
                Route::get('/', [DeviceManagementController::class, 'show'])->name('show');
                Route::get('/api/status', [DeviceManagementController::class, 'apiStatus'])->name('api-status');
                Route::post('/sinkron-jam', [DeviceManagementController::class, 'syncTime'])->name('sync-time');
                Route::post('/tarik-log', [DeviceManagementController::class, 'queryLogs'])->name('query-logs');
                Route::post('/buka-kunci-admin', [DeviceManagementController::class, 'unlockAdmin'])->name('unlock-admin');
                Route::post('/reboot', [DeviceManagementController::class, 'reboot'])->name('reboot');
                Route::post('/bersihkan-log-mesin', [DeviceManagementController::class, 'clearDeviceLogs'])->name('clear-logs');
                Route::post('/hapus-siswa-lulus', [DeviceManagementController::class, 'purgeGraduated'])->name('purge-graduated');
                Route::post('/kosongkan-semua-user', [DeviceManagementController::class, 'clearAllUsers'])->name('clear-all-users');
                Route::post('/sinkron-kelompok-user', [DeviceManagementController::class, 'syncTargetUsers'])->name('sync-target-users');
                Route::post('/kirim-perintah-custom', [DeviceManagementController::class, 'sendCustomCommand'])->name('custom-command');
                Route::post('/cadangkan-biometrik', [DeviceManagementController::class, 'backupBiometrics'])->name('backup-biometrics');
                Route::post('/kloning-biometrik', [DeviceManagementController::class, 'cloneBiometrics'])->name('clone-biometrics');
                Route::post('/batalkan-antrean', [DeviceManagementController::class, 'cancelPendingCommands'])->name('cancel-queue');
            });
        });
    });
});
