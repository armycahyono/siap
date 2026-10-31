<?php

use App\Http\Controllers\Api\Mobile\MobileAttendanceController;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileDashboardController;
use App\Http\Controllers\Api\Mobile\MobileKbmController;
use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use App\Http\Controllers\Api\Mobile\MobileProfileController;
use App\Http\Controllers\Api\Mobile\MobileSupervisionController;
use App\Http\Controllers\Api\Mobile\MobileTasksController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your application. These
| routes are loaded by the RouteServiceProvider or bootstrap/app.php
|
*/

Route::prefix('v1/mobile')->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
])->group(function () {
    // 1. Auth & Session Management
    Route::post('auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('auth/me', [MobileAuthController::class, 'me']);
    Route::post('auth/logout', [MobileAuthController::class, 'logout']);

    // 2. Core Mobile Data Endpoints
    Route::get('dashboard', [MobileDashboardController::class, 'index']);
    Route::get('kbm', [MobileKbmController::class, 'index']);
    Route::get('kbm/take', [MobileKbmController::class, 'getTakeData']);
    Route::post('kbm/take/store', [MobileKbmController::class, 'storeAttendance']);
    Route::get('tasks', [MobileTasksController::class, 'index']);
    Route::get('students/search', [MobileTasksController::class, 'searchStudents']);

    // Sub-modul Tugas Khusus Mobile (In-App Pages)
    Route::get('tasks/permits', [MobileTasksController::class, 'getPermitsData']);
    Route::post('tasks/permits/store', [MobileTasksController::class, 'storeGtkPermit']);
    Route::post('tasks/permits/{id}/action', [MobileTasksController::class, 'actionGtkPermit']);

    Route::get('tasks/visitations', [MobileTasksController::class, 'getVisitationsData']);
    Route::post('tasks/visitations/store', [MobileTasksController::class, 'storeVisitation']);

    Route::get('tasks/discipline', [MobileTasksController::class, 'getDisciplineData']);
    Route::post('tasks/discipline/verify', [MobileTasksController::class, 'verifyDisciplineIncident']);

    Route::get('tasks/bk', [MobileTasksController::class, 'getBkData']);
    Route::post('tasks/bk/services/store', [MobileTasksController::class, 'storeBkService']);
    Route::post('tasks/bk/status', [MobileTasksController::class, 'updateBkReferralStatus']);

    // Sub-modul Meja Piket Mobile (In-App Workspace & Operasional Cepat)
    Route::get('tasks/piket', [MobileTasksController::class, 'getPiketData']);
    Route::get('tasks/piket/invals/teacher-schedules', [MobileTasksController::class, 'getTeacherSchedulesForInval']);
    Route::post('tasks/piket/permits/store', [MobileTasksController::class, 'storePiketStudentPermit']);
    Route::post('tasks/piket/incidents/store', [MobileTasksController::class, 'storePiketIncident']);
    Route::post('tasks/piket/invals/store', [MobileTasksController::class, 'storePiketInval']);
    Route::post('tasks/piket/guests/store', [MobileTasksController::class, 'storePiketGuest']);
    Route::post('tasks/piket/guests/{id}/checkout', [MobileTasksController::class, 'checkoutPiketGuest']);
    Route::post('tasks/piket/journal/close', [MobileTasksController::class, 'closePiketJournal']);
    Route::get('tasks/piket/swaps', [MobileTasksController::class, 'getPiketDutySwaps']);
    Route::post('tasks/piket/swaps/store', [MobileTasksController::class, 'storePiketDutySwap']);
    Route::post('tasks/piket/swaps/{id}/action', [MobileTasksController::class, 'actionPiketDutySwap']);

    // Sub-modul Supervisi Guru Mobile (Praobservasi, Observasi Cepat, Foto Kamera, Pascaobservasi & Approval)
    Route::get('tasks/supervisions', [MobileSupervisionController::class, 'index']);
    Route::get('tasks/supervisions/{id}', [MobileSupervisionController::class, 'show']);
    Route::post('tasks/supervisions/{id}/rpp', [MobileSupervisionController::class, 'uploadRpp']);
    Route::post('tasks/supervisions/{id}/praobservasi', [MobileSupervisionController::class, 'savePreObservation']);
    Route::post('tasks/supervisions/{id}/observasi', [MobileSupervisionController::class, 'saveObservation']);
    Route::post('tasks/supervisions/{id}/pascaobservasi', [MobileSupervisionController::class, 'savePostObservation']);
    Route::post('tasks/supervisions/{id}/photos', [MobileSupervisionController::class, 'uploadPhoto']);
    Route::delete('tasks/supervisions/photos/{photoId}', [MobileSupervisionController::class, 'deletePhoto']);
    Route::post('tasks/supervisions/{id}/finalize', [MobileSupervisionController::class, 'finalize']);
    Route::post('tasks/supervisions/{id}/approve', [MobileSupervisionController::class, 'approve']);
    Route::get('tasks/supervisions/photos/{photoId}/view', [MobileSupervisionController::class, 'viewPhoto'])->name('api.mobile.tasks.supervisions.photo');
    Route::get('tasks/supervisions/{id}/rpp/download', [MobileSupervisionController::class, 'downloadRpp'])->name('api.mobile.tasks.supervisions.rpp');

    Route::get('profile', [MobileProfileController::class, 'index']);
    Route::get('profile/activity-logs', [MobileProfileController::class, 'getActivityLogs']);
    Route::get('notifications', [MobileNotificationController::class, 'index']);
    Route::post('profile/update', [MobileAuthController::class, 'updateProfile']);
    Route::post('profile/password', [MobileAuthController::class, 'updatePassword']);

    // 3. Attendance & Geotagging
    Route::get('attendance/status', [MobileAttendanceController::class, 'status']);
    Route::post('attendance/store', [MobileAttendanceController::class, 'store']);
});
