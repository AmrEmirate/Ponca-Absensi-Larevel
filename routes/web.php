<?php

use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Hello! API Unified Ponca Food Jaya berjalan dengan lancar.',
        'status' => 'success'
    ]);
});

Route::get('/sso', [SsoController::class, 'login'])->name('sso.login');
