<?php

namespace App\Http\Controllers\Auth;

use App\Common\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuthService\AuthServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request, AuthServices $authServices)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'name' => 'required','string',
            'password' => ['required', 'string', 'min:8', 'max:20', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }
        try {
            DB::beginTransaction();
            $user = User::create([
                'name' => $request->input('name'),
                'username' => $authServices->username(),
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password')),
            ]);
            UserProfile::create([
                'user_id' => $user->id,
                'name' => $request->input('name'),
                'username' => $authServices->username(),
                'email' => $request->input('email'),
            ]);
            $token = $authServices->generateAccessToken($user);
            DB::commit();
            return $authServices->respondWithToken($user, $token, 'User registered successfully');
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Oops! Something went wrong. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('username_or_email', 'password');

        $field = filter_var($credentials['username_or_email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials[$field] = $credentials['username_or_email'];
        unset($credentials['username_or_email']);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('API Token')->accessToken;

            return response()->json([
                'message' => 'Successfully logged in',
                'token' => $token
            ], 201);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function logout()
    {
        auth()->user()->token()->revoke();

        return response()->json(['message' => 'Successfully logged out'], 201);
    }
}
