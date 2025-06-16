<?php

use GuzzleHttp\Middleware;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\StockController;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Protected routes can be added here
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


// API Routes
Route::prefix('v1')->group(function () {
    Route::get('/stocks', [StockController::class, 'index']);
    Route::get('/stocks/{symbol}', [StockController::class, 'show']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/news/{symbol}', [NewsController::class, 'forStock']);
});
