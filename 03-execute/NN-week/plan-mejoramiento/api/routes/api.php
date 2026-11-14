<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/v1/auth/login', [AuthController::class, 'login']);