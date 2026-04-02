<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::post('/login', [UserController::class, 'login']);
Route::get('/welcome2', [UserController::class, 'welcome2']);
        // route name, Class name,             method name
// Route::post();
// Route::any();