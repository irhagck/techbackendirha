<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function login(Request $req)
    {
        // Validate request
        $req->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        // Attempt login
        if (!Auth::attempt(['email' => $req->email, 'password' => $req->password])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Regenerate session
        $req->session()->regenerate();

        $user = Auth::user();

        return response()->json([
            'message' => 'Login successful',
            'user'    => $user,
        ], 200);
    }

    public function welcome2(){
        return "BS CS";
    }
}