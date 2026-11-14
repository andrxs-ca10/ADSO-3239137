<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MemberController;

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
    Route::post('/v1/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem']);
    Route::get('/v1/loans/overdue', [LoanController::class, 'overdue']);
    Route::get('/v1/categories', [CategoryController::class, 'index']);
    Route::get('/v1/members', [MemberController::class, 'index']);
    Route::get('/v1/members/{id}', [MemberController::class, 'show']);
});

Route::middleware(AuthenticateJwt::class.':admin')->group(function () {
    Route::post('/v1/games', [GameController::class, 'store']);
    Route::put('/v1/games/{id}', [GameController::class, 'update']);
    Route::delete('/v1/games/{id}', [GameController::class, 'destroy']);
    Route::get('/v1/reports/most-requested', [ReportController::class, 'mostRequested']);
});