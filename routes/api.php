<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\IzinController;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\AdminOnly;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Endpoint paths IDENTIK dengan backend Node.js agar Android app
| tidak perlu diubah. Prefix /api sudah otomatis dari Laravel.
|--------------------------------------------------------------------------
*/

// === Health Check ===
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()->toISOString()]);
});

// === Auth Routes ===
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(JwtAuth::class)->group(function () {
        Route::post('/verify-pin', [AuthController::class, 'verifyPin']);
        Route::get('/me', [AuthController::class, 'getProfile']);
        Route::post('/register-face', [AuthController::class, 'registerFace']);
        Route::post('/request-face-reverification', [AuthController::class, 'requestFaceReverification']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/karyawan', [AuthController::class, 'getAllKaryawan']);
    });
});

// === Attendance Routes ===
Route::prefix('attendance')->middleware(JwtAuth::class)->group(function () {
    Route::post('/check-in', [AttendanceController::class, 'checkIn'])->middleware('throttle:30,1');
    Route::post('/check-out', [AttendanceController::class, 'checkOut'])->middleware('throttle:30,1');
    Route::get('/history', [AttendanceController::class, 'getHistory']);
    Route::get('/geofence', [AttendanceController::class, 'getGeofence']);
});

// === Admin Routes (JWT + Admin middleware) ===
Route::prefix('admin')->middleware([JwtAuth::class, AdminOnly::class])->group(function () {
    Route::get('/report', [AdminController::class, 'getReport']);
    Route::get('/export', [AdminController::class, 'exportExcel']);
    Route::get('/geofence', [AdminController::class, 'getGeofence']);
    Route::post('/geofence', [AdminController::class, 'updateGeofence']);
    Route::get('/salary-report', [AdminController::class, 'getSalaryReport']);
    Route::get('/export-salary', [AdminController::class, 'exportSalaryExcel']);

    // Employee CRUD
    Route::get('/employees/next-nik', [AdminController::class, 'getNextNik']);
    Route::get('/employees', [AdminController::class, 'getEmployees']);
    Route::get('/employees/{id}', [AdminController::class, 'getEmployeeById']);
    Route::post('/employees', [AdminController::class, 'createEmployee']);
    Route::put('/employees/{id}', [AdminController::class, 'updateEmployee']);
    Route::delete('/employees/{id}', [AdminController::class, 'deleteEmployee']);
    Route::post('/employees/{id}/face', [AdminController::class, 'uploadFaceReference']);
    Route::post('/employees/import', [AdminController::class, 'importExcelEmployees']);

    // Location CRUD (Master Pabrik & Outlet)
    Route::get('/lokasi', [AdminController::class, 'getLokasis']);
    Route::post('/lokasi', [AdminController::class, 'createLokasi']);
    Route::put('/lokasi/{id}', [AdminController::class, 'updateLokasi']);
    Route::delete('/lokasi/{id}', [AdminController::class, 'deleteLokasi']);

    // Face Reverification
    Route::get('/face-reverifications', [AdminController::class, 'getPendingFaceReverifications']);
    Route::post('/face-reverifications/{id}/approve', [AdminController::class, 'approveFaceReverification']);
    Route::post('/face-reverifications/{id}/reject', [AdminController::class, 'rejectFaceReverification']);
});

// === Public/Auth Lokasi List (untuk Dropdown Form) ===
Route::get('/lokasi', [AdminController::class, 'getLokasis'])->middleware(JwtAuth::class);

// === Izin Routes ===
Route::prefix('izin')->middleware(JwtAuth::class)->group(function () {
    // Karyawan endpoints
    Route::post('/', [IzinController::class, 'createIzin']);
    Route::get('/me', [IzinController::class, 'getMyIzin']);

    // Admin endpoints
    Route::middleware(AdminOnly::class)->group(function () {
        Route::get('/all', [IzinController::class, 'getAllIzin']);
        Route::put('/{id}/status', [IzinController::class, 'updateIzinStatus']);
    });
});
