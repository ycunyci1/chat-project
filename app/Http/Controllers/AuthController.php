<?php

namespace App\Http\Controllers;

use Dd1\Chat\Models\User;
use Dd1\Chat\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginPage()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'string|required',
            'password' => 'string|required',
        ]);
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            return response()->json([
                'message' => 'Неверный логин или пароль',
            ], 422);
        }

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Неверный логин или пароль',
            ], 422);
        }

        return response()->json([
            'centrifugo_token' => JwtService::generateJwt($user->id),
            'access_token' => $user->createToken('AUTO_TOKEN')->accessToken,
            'user_id' => $user->id,
        ]);
    }

    public function registerPage()
    {
        return view('register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'string|required',
            'last_name' => 'string|required',
            'email' => 'string|email|required',
            'password' => 'string|required',
            'avatar' => 'file|required',
        ]);
        $data['name'] = $data['name'].' '.$data['last_name'];
        $data['email_verified_at'] = now();
        $file = $request->file('avatar');
        $filename = time().'.'.$file->getClientOriginalExtension();
        $file->storeAs('files', $filename, 'public');
        $data['avatar'] = config('app.url').'/storage/files/'.$filename;
        $data['password'] = Hash::make($data['password']);
        $user = User::query()->create($data);

        return response()->json([
            'centrifugo_token' => JwtService::generateJwt($user->id),
            'access_token' => $user->createToken('AUTO_TOKEN')->accessToken,
            'user_id' => $user->id,
        ]);
    }
}
