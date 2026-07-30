<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Hello! API Ponca Food Absensi berjalan dengan lancar.',
        'status' => 'success'
    ]);
});
