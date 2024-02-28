<?php

namespace App\Services\AuthService;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthServices
{
    /**
     * Attempt Login
     */
    public function attemptLogin(array $credentials)
    {
        return Auth::attempt($credentials);
    }

    /**
     * Unauthorized Response
     */
    public function unauthorizedResponse()
    {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Generate Access Token
     */
    public function generateAccessToken($user): string
    {
        return $user->createToken('token')->accessToken;
    }

    /**
     * Respond With Token
     */
    public function respondWithToken($user, $token = null, $message = 'Success')
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token ?: $this->generateAccessToken($user),
            'token_type' => 'bearer'
        ], 200);
    }

    /**
     * Validator
     */
    public function validator(Request $request)
    {
        Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'phone' => ['numeric', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /*
     * Username Generate
     */
    public function username()
    {
        $baseUsername = preg_replace('/[\s_]+/', '', strtolower(request()->input('name')));
        $username = $baseUsername;

        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $pin = mt_rand(1, 9)
            .mt_rand(1, 9)
            . $characters[rand(0, strlen($characters) - 1)]
            . $characters[rand(0, strlen($characters) - 1)];
        $string = str_shuffle($pin);
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $string;
        }
        return $username;
    }
}
