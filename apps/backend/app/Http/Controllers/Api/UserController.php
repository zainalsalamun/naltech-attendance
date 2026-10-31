<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //get by user id
    public function getUserId(Request $request, $id)
    {
        abort_unless((int) $id === (int) $request->user()->id, 403);
        $user = $request->user();

        return response([
            'status' => 'Success',
            'message' => 'User found',
            'data' => $user,
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        abort_unless((int) $request->id === (int) $request->user()->id, 403);
        $user = $request->user();
        $data = $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('images/users', 'public');
            abort_unless(is_string($path) && $path !== '', 503, 'Foto belum dapat disimpan. Coba lagi.');
            $user->image_url = $path;
        }
        $user->save();

        return response(['status' => 'Success', 'message' => 'Update user success', 'data' => $user], 200);
    }
}
