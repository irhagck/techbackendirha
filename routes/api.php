<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->get('/profile', [AuthController::class, 'profile']);