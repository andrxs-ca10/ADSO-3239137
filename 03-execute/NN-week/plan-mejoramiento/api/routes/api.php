<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoanController;

Route::get('/v1/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/v1/auth/login', [AuthController::class, 'login']);

Route::post('/v1/users', [UserController::class, 'store'])
    ->middleware(AuthenticateJwt::class.':admin');

Route::middleware(AuthenticateJwt::class.':admin,operator')->group(function () {
    Route::get('/v1/games', [GameController::class, 'index']);
    Route::get('/v1/games/{id}', [GameController::class, 'show']);
    Route::post('/v1/loans', [LoanController::class, 'store']);
});

Route::middleware(AuthenticateJwt::class.':admin')->group(function () {
    Route::post('/v1/games', [GameController::class, 'store']);
    Route::put('/v1/games/{id}', [GameController::class, 'update']);
    Route::delete('/v1/games/{id}', [GameController::class, 'destroy']);
});
    Route::post('/v1/loans', [LoanController::class, 'store'])
    ->middleware(AuthenticateJwt::class.':admin,operator');