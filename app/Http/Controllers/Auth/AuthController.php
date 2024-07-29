<?php

namespace App\Http\Controllers\Auth;

use App\Common\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuthService\AuthServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        $otp = rand(100000, 999999);
        try {
            DB::beginTransaction();
            $username = $authServices->username($request->input('name'));
            $user = User::create([
                'name' => $request->input('name'),
                'username' => $username,
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password')),
                'otp' => $otp,
                'otp_created_at' => now(),
            ]);
            UserProfile::create([
                'user_id' => $user->id,
                'name' => $request->input('name'),
                'username' => $username,
                'email' => $request->input('email'),
            ]);
            $token = $authServices->generateAccessToken($user);
            // Send OTP to the user's email
            Mail::send('auth.verify_email', ['otp' => $otp], function($message) use ($request) {
                $message->to($request->email)
                    ->subject('Email Verification OTP');
            });
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
                'user' => auth()->user()->only(['name', 'username', 'email']),
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

    public function forgotPassword(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        try {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                $query = PasswordResetToken::updateOrCreate(
                    ['email' => $request->email],
                    [
                        'email' => $request->email,
                        'token' => Str::random(60),
                        'created_at' => now()
                    ]
                );
                return response()->json([
                    'message' => 'Please submit your new password.',
                    'token' => $query->token
                ], 201);
            } else {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'Unable to send reset password email. Please try again later.',
                'error' => $exception->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $query = PasswordResetToken::where([
            ['token', $request->token],
            ['email', $request->email]
        ])->first();

        if (!$query) {
            return response()->json(["message" => "Invalid or expired password reset token."], 404);
        }

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();
            PasswordResetToken::where('email', $request->email)->delete();
            return response()->json(["message" => "Your password has been reset successfully."], 201);
        }
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'otp' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->where('otp', $request->otp)->first();

        if ($user) {
            $otpCreationTime = Carbon::parse($user->otp_created_at);
            if ($otpCreationTime->diffInMinutes(now()) <= 5) {
                $user->email_verified_at = now();
                $user->otp = null;
                $user->otp_created_at = null;
                $user->save();

                return response()->json(['message' => 'Email verified successfully.'], 201);
            } else {
                return response()->json(['message' => 'OTP has expired.'], 400);
            }
        } else {
            return response()->json(['message' => 'Invalid OTP or email.'], 400);
        }
    }
}
