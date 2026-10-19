<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

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
            'data' => $user
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required',
                'name' => 'required',
                'email' => 'required|email',
                'phone' => 'required',
                'image_url' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            abort_unless((int) $request->id === (int) $request->user()->id, 403);
            $user = $request->user();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = time() . '.' . $image->getClientOriginalExtension();
                $filePath = $image->storeAs('images/users', $image_name, 'public');
                $user->image_url = $filePath;
            }
            $user->save();
            return response([
                'status' => 'Success',
                'message' => 'Update user success',
                'data' => $user,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $th) {
            return response([
                'message' => $th->getMessage(),
            ]);
        }
    }
}
