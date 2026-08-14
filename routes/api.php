<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\IzinController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\SyncApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\CloudinaryUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\AdminOnly;

/*
|--------------------------------------------------------------------------
| API Routes — Unified Backend (Mobile Absensi & Web POS)
|--------------------------------------------------------------------------
*/

// === Health Check ===
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()->toISOString()]);
});

// === Mobile App Auth Routes ===
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

// === Mobile Admin Routes ===
Route::prefix('admin')->middleware([JwtAuth::class, AdminOnly::class])->group(function () {
    Route::get('/report', [AdminController::class, 'getReport']);
    Route::get('/export', [AdminController::class, 'exportExcel']);
    Route::get('/geofence', [AdminController::class, 'getGeofence']);
    Route::post('/geofence', [AdminController::class, 'updateGeofence']);
    Route::get('/salary-report', [AdminController::class, 'getSalaryReport']);
    Route::get('/export-salary', [AdminController::class, 'exportSalaryExcel']);

    Route::get('/employees/next-nik', [AdminController::class, 'getNextNik']);
    Route::get('/employees', [AdminController::class, 'getEmployees']);
    Route::get('/employees/{id}', [AdminController::class, 'getEmployeeById']);
    Route::post('/employees', [AdminController::class, 'createEmployee']);
    Route::put('/employees/{id}', [AdminController::class, 'updateEmployee']);
    Route::delete('/employees/{id}', [AdminController::class, 'deleteEmployee']);
    Route::post('/employees/{id}/face', [AdminController::class, 'uploadFaceReference']);
    Route::post('/employees/import', [AdminController::class, 'importExcelEmployees']);

    Route::get('/lokasi', [AdminController::class, 'getLokasis']);
    Route::post('/lokasi', [AdminController::class, 'createLokasi']);
    Route::put('/lokasi/{id}', [AdminController::class, 'updateLokasi']);
    Route::delete('/lokasi/{id}', [AdminController::class, 'deleteLokasi']);

    Route::get('/face-reverifications', [AdminController::class, 'getPendingFaceReverifications']);
    Route::post('/face-reverifications/{id}/approve', [AdminController::class, 'approveFaceReverification']);
    Route::post('/face-reverifications/{id}/reject', [AdminController::class, 'rejectFaceReverification']);
});

Route::get('/lokasi', [AdminController::class, 'getLokasis'])->middleware(JwtAuth::class);

// === Izin Routes ===
Route::prefix('izin')->middleware(JwtAuth::class)->group(function () {
    Route::post('/', [IzinController::class, 'createIzin']);
    Route::get('/me', [IzinController::class, 'getMyIzin']);

    Route::middleware(AdminOnly::class)->group(function () {
        Route::get('/all', [IzinController::class, 'getAllIzin']);
        Route::put('/{id}/status', [IzinController::class, 'updateIzinStatus']);
    });
});

// === Notification Routes ===
Route::prefix('notifications')->middleware(JwtAuth::class)->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
});

// === Web POS SPA Routes (Public & Sanctum) ===
Route::post('/pos/login', [AuthApiController::class, 'login'])->name('api.pos.login');

Route::middleware('auth:web')->group(function () {
    Route::post('/pos/logout', [AuthApiController::class, 'logout'])->name('api.pos.logout');
    Route::get('/me', [AuthApiController::class, 'me'])->name('api.me');
    Route::post('/change-password', [AuthApiController::class, 'changePassword'])->name('api.change-password');
    Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('api.dashboard');

    Route::get('/products', [ProductApiController::class, 'index'])->name('api.products.index');
    Route::post('/products', [ProductApiController::class, 'store'])->name('api.products.store');
    Route::post('/products/{itemCode}/update-image', [ProductApiController::class, 'updateImage'])->name('api.products.update-image');

    Route::get('/customers', [CustomerApiController::class, 'index'])->name('api.customers.index');

    Route::get('/orders', [OrderApiController::class, 'index'])->name('api.orders.index');
    Route::post('/orders', [OrderApiController::class, 'store'])->name('api.orders.store');
    Route::post('/orders/{id}/update', [OrderApiController::class, 'update'])->name('api.orders.update');
    Route::delete('/orders/{id}', [OrderApiController::class, 'destroy'])->name('api.orders.destroy');
    Route::post('/orders/{id}/retry-sync', [OrderApiController::class, 'retrySync'])->name('api.orders.retry-sync');
    Route::post('/orders/{id}/toggle-verification', [OrderApiController::class, 'toggleVerification'])->name('api.orders.toggle-verification');

    Route::get('/users', [UserApiController::class, 'index'])->name('api.users.index');
    Route::post('/users', [UserApiController::class, 'store'])->name('api.users.store');
    Route::post('/users/{id}/update', [UserApiController::class, 'update'])->name('api.users.update');
    Route::post('/users/{id}/toggle-status', [UserApiController::class, 'toggleStatus'])->name('api.users.toggle-status');
    Route::post('/users/{id}/reset-password', [UserApiController::class, 'resetPassword'])->name('api.users.reset-password');

    Route::get('/sync/datasets', [SyncApiController::class, 'datasets'])->name('api.sync.datasets');
    Route::get('/sync/config', [SyncApiController::class, 'config'])->name('api.sync.config');
    Route::post('/sync/all', [SyncApiController::class, 'syncAll'])->name('api.sync.all');
    Route::post('/sync/single', [SyncApiController::class, 'syncSingle'])->name('api.sync.single');
    Route::post('/sync/config', [SyncApiController::class, 'saveConfig'])->name('api.sync.save-config');

    Route::post('/cloudinary/upload', [CloudinaryUploadController::class, 'upload'])->name('api.cloudinary.upload');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('api.profile.avatar');
});
