<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Api\FactoryController;

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
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

// Route::post('/login', [UserController::class, 'login']);
Route::get('/welcome2', [UserController::class, 'welcome2']);

Route::prefix('factories')->group(function () {

    Route::get('allfactories', [FactoryController::class, 'index']);
    Route::post('addfactory', [FactoryController::class, 'store']);
    Route::get('editfactory/{id}', [FactoryController::class, 'show']);
    Route::put('updatefactory/{id}', [FactoryController::class, 'update']);
    Route::delete('deletefactory/{id}', [FactoryController::class, 'destroy']);

});
