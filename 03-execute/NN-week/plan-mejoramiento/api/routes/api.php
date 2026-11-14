<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameCoverController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/v1/auth/login', [AuthController::class, 'login']);

Route::post('/v1/users', [UserController::class, 'store'])
    ->middleware(AuthenticateJwt::class.':admin');

Route::middleware(AuthenticateJwt::class.':admin,operator')->group(function () {
    Route::get('/v1/games', [GameController::class, 'index']);
    Route::get('/v1/games/{id}', [GameController::class, 'show']);
    Route::get('/v1/games/{id}/cover', [GameCoverController::class, 'show']);
    Route::get('/v1/loans', [LoanController::class, 'index']);

    Route::post('/v1/loans', [LoanController::class, 'store']);
    Route::get('/v1/loans/overdue', [LoanController::class, 'overdue']);
    Route::get('/v1/loans/{id}', [LoanController::class, 'show']);
    Route::post('/v1/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem']);

    Route::get('/v1/categories', [CategoryController::class, 'index']);

    Route::get('/v1/members', [MemberController::class, 'index']);
    Route::get('/v1/members/{id}', [MemberController::class, 'show']);
    Route::post('/v1/members', [MemberController::class, 'store']);
});

Route::middleware(AuthenticateJwt::class.':admin')->group(function () {
    Route::post('/v1/games', [GameController::class, 'store']);
    Route::put('/v1/games/{id}', [GameController::class, 'update']);
    Route::delete('/v1/games/{id}', [GameController::class, 'destroy']);
    Route::put('/v1/games/{id}/cover', [GameCoverController::class, 'store']);
    Route::delete('/v1/games/{id}/cover', [GameCoverController::class, 'destroy']);
    Route::get('/v1/reports/most-requested', [ReportController::class, 'mostRequested']);
});