<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiToken;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])
            ->where('is_active', true)
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid username or password',
            ], 401);
        }

        $plainToken = Str::random(80);

        ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return response()->json([
            'message' => 'Login successful',
            'token' => $plainToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->attributes->get('api_token')?->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'navigation' => $user->navigation(),
        ];
    }
}
