<?php

<<<<<<< HEAD
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->get('/profile', [AuthController::class, 'profile']);
=======
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::post('/login', [UserController::class, 'login']);
Route::get('/welcome2', [UserController::class, 'welcome2']);
        // route name, Class name,             method name
// Route::post();
// Route::any();
>>>>>>> 62157d1e1151241d9c810343c31193832040f8f1
