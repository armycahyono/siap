<?php

use App\Http\Controllers\Api\Mobile\MobileAttendanceController;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileDashboardController;
use App\Http\Controllers\Api\Mobile\MobileKbmController;
use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use App\Http\Controllers\Api\Mobile\MobileProfileController;
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
    Route::post('auth/login', [MobileAuthController::class, 'login']);
    Route::get('auth/me', [MobileAuthController::class, 'me']);
    Route::post('auth/logout', [MobileAuthController::class, 'logout']);

    // 2. Core Mobile Data Endpoints
    Route::get('dashboard', [MobileDashboardController::class, 'index']);
    Route::get('kbm', [MobileKbmController::class, 'index']);
    Route::get('tasks', [MobileTasksController::class, 'index']);

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
    Route::get('profile', [MobileProfileController::class, 'index']);
    Route::get('notifications', [MobileNotificationController::class, 'index']);
    Route::post('profile/update', [MobileAuthController::class, 'updateProfile']);
    Route::post('profile/password', [MobileAuthController::class, 'updatePassword']);

    // 3. Attendance & Geotagging
    Route::get('attendance/status', [MobileAttendanceController::class, 'status']);
    Route::post('attendance/store', [MobileAttendanceController::class, 'store']);
});
