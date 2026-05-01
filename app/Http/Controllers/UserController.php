<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // ✅ 1. Show All Users
    public function index()
    {
        $users = User::all();

        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }

    // ✅ 2. Add User
    public function store(Request $request)
    {
        $request->validate([
         'name'              => 'required|string|max:255',
         'email'             => 'required|email|unique:users,email',
         'password'          => 'required|min:6',
         'phone_no'          => 'required|string|max:20',
         'cnic'              => 'required|string|max:20|unique:users,cnic',
         'address'           => 'nullable|string',
         'pic'               => 'nullable|string',
         'role'              => 'required|string',
         'employee_details'  => 'nullable|string',
        ]);

        $user = User::create([
         'name'              => $request->name,
         'email'             => $request->email,
         'password'          => Hash::make($request->password),
         'phone_no'          => $request->phone_no,
         'cnic'              => $request->cnic,
         'address'           => $request->address,
         'pic'               => $request->pic,
         'role'              => $request->role,
         'employee_details'  => $request->employee_details,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data'    => $user
        ], 201);
    }

    // ✅ 3. Get Single User (Edit)
    public function edit($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $user
        ], 200);
    }

    // ✅ 4. Update User
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

            $request->validate([
             'name'              => 'sometimes|string|max:255',
             'email'             => 'sometimes|email|unique:users,email,' . $id,
             'password'          => 'sometimes|min:6',
             'phone_no'          => 'sometimes|string|max:20',
             'cnic'              => 'sometimes|string|max:20|unique:users,cnic,' . $id,
             'address'           => 'nullable|string',
             'pic'               => 'nullable|string',
             'role'              => 'sometimes|string',
             'employee_details'  => 'nullable|string',
            ]);

        $user->name = $request->name ?? $user->name;
        $user->email = $request->email ?? $user->email;
        $user->phone_no = $request->phone_no ?? $user->phone_no;
        $user->phone_no = $request->phone_no ?? $user->phone_no;
        $user->cnic = $request->cnic ?? $user->cnic;
        $user->address = $request->address ?? $user->address;
        $user->pic = $request->pic ?? $user->pic;
        $user->role = $request->role ?? $user->role;
        $user->employee_details = $request->employee_details ?? $user->employee_details;

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data'    => $user
        ], 200);
    }

    // ✅ 5. Delete User
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ], 200);
    }
}

