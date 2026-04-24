<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|confirmed|min:6'
        ]);

        $user = User::create($data);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => 'required|string|exists:users,email',
                'password' => 'required|string|min:5'
            ]);

            $user = User::where('email', $data['email'])->first();
            if (!$user || !Hash::check($data['password'], $user->password)) {
                return response([
                    'message' => 'Incorrect username or password'
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response([
                'user' => $user,
                'token' => $token
            ]);
        } catch (Exception $e) {
            // Logging error harian
            LogHelper::logError('Proses Simpan Absensi', $e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 500);
        }
        
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response([
            'message' => 'User logged out'
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string|min:6',
            'new_password' => 'required|string|confirmed|min:6'
        ]);

        if (!Hash::check($request->old_password, $request->user()->password)) {
            return response([
                'message' => 'Old password doesn\'t match'
            ], 401);
        }

        User::whereId($request->user()->id)->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response([
            'message' => 'Password changed successfully'
        ]);
    }
}
