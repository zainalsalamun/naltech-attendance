<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //login
    public function login(Request $request)
    {
        $loginData = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($loginData) {
            $user = User::withoutGlobalScopes()->where('email', $loginData['email'])->lockForUpdate()->first();

            //check user exist
            if (! $user) {
                return response(['message' => 'Invalid credentials'], 401);
            }

            //check password
            if (! Hash::check($loginData['password'], $user->password)) {
                return response(['message' => 'Invalid credentials'], 401);
            }

            if (! $user->isEmploymentActive()) {
                return response(['message' => 'Akun nonaktif. Hubungi HR/admin.'], 401);
            }
            $token = $user->createToken('auth_token')->plainTextToken;

            return response(['user' => $user, 'token' => $token], 200);
        });
    }

    //logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response(['message' => 'Logged out'], 200);
    }

    //update image profile & face_embedding
    public function updateProfile(Request $request)
    {
        abort(410, 'Pendaftaran embedding dari perangkat tidak lagi didukung. Gunakan pendaftaran wajah dengan persetujuan HR/admin.');
    }

    //update fcm token
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response([
            'message' => 'FCM token updated',
        ], 200);
    }
}
