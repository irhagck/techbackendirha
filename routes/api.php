<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/test', function(){
    $user = User::create([
            'name' => 'írha',
            'email' => 'test@test.com',
            'password' => Hash::make('test@test.com'),
        ]);

        return response()->json([
            'message' => 'User created',
            'data' => $user
        ], 201);
});

Route::middleware('auth:sanctum')->get('/profile', [AuthController::class, 'profile']);