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
        $otp = random_int(100000, 999999);
        try {
            DB::beginTransaction();
            $username = $authServices->username($request->input('name'));
            $user = User::create([
                'name' => $request->input('name'),
                'username' => $username,
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'otp' => $otp,
                'otp_created_at' => now(),
            ]);
            UserProfile::create([
                'user_id' => $user->id,
                'name' => $request->input('name'),
                'username' => $username,
                'email' => $request->input('email'),
            ]);
            Mail::send('auth.verify_email', ['otp' => $otp], function($message) use ($request) {
                $message->to($request->email)
                    ->subject('Email Verification OTP');
            });
            DB::commit();
            return response()->json([
                "message" => "User registered successfully"
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Oops! Something went wrong. Please try again later.',
            ], 401);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username_or_email' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $credentials = $request->only('username_or_email', 'password');
        $field = filter_var($credentials['username_or_email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials[$field] = $credentials['username_or_email'];
        unset($credentials['username_or_email']);

        $user = User::where($field, $credentials[$field])->first();

        if ($user && !$user->email_verified_at) {
            return response()->json(['error' => 'Email not verified'], 401);
        }

        if ($user && Hash::check($credentials['password'], $user->password)) {
            $token = $user->createToken('API Token')->accessToken;
            Auth::login($user);
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
//        auth()->user()->token()->revoke();
        auth()->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out'], 201);
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
            if ($otpCreationTime->diffInMinutes(now()) <= 60) {
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

    public function verifyEmailResendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $newOtp = random_int(100000, 999999);
            $user->otp = $newOtp;
            $user->otp_created_at = now();
            $user->save();
            Mail::send('auth.verify_email', ['otp' => $newOtp], function($message) use ($user) {
                $message->to($user->email)
                    ->subject('Email Verification OTP');
            });
            return response()->json(['message' => 'OTP has been resent.'], 200);
        } else {
            return response()->json(['message' => 'Email not found.'], 404);
        }
    }

    public function forgotPassword(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        try {
            $user = User::where('email', $request->email)->first();
            $otp = random_int(100000, 999999);
            if ($user) {
                PasswordResetToken::updateOrCreate(
                    ['email' => $request->email],
                    [
                        'email' => $request->email,
                        'token' => $otp,
                        'created_at' => now()
                    ]
                );
                Mail::send('auth.forgot_password_token', ['otp' => $otp], function($message) use ($request) {
                    $message->to($request->email)
                        ->subject('Forgot Password OTP');
                });
                return response()->json([
                    'message' => 'OTP has been sent to your email.',
                ], 201);
            } else {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Unable to send reset password email. Please try again later.'
            ], 500);
        }
    }

    public function resetPassword(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:8|confirmed'
        ]);

        try {
            $tokenData = PasswordResetToken::where('email', $request->email)->where('token', $request->otp)->first();

            if ($tokenData) {
                $otpCreationTime = Carbon::parse($tokenData->created_at);
                if ($otpCreationTime->diffInMinutes(now()) <= 60) {
                    $user = User::where('email', $request->email)->first();
                    if ($user) {
                        $user->password = Hash::make($request->password);
                        $user->save();

                        PasswordResetToken::where('email', $request->email)->delete();
                        return response()->json([
                            'message' => 'Password has been reset successfully.'
                        ], 201);
                    } else {
                        return response()->json([
                            'message' => 'User not found'
                        ], 404);
                    }
                } else {
                    return response()->json([
                        'message' => 'OTP has expired.'
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'Invalid OTP or email.'
                ], 400);
            }
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Unable to reset password. Please try again later.'
            ], 500);
        }
    }

    public function forgotPasswordResendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $newOtp = random_int(100000, 999999);
            $user->otp = $newOtp;
            $user->otp_created_at = now();
            $user->save();
            Mail::send('auth.forgot_password_token', ['otp' => $newOtp], function($message) use ($user) {
                $message->to($user->email)
                    ->subject('Forgot Password OTP');
            });
            return response()->json(['message' => 'OTP has been resent.'], 200);
        } else {
            return response()->json(['message' => 'Email not found.'], 404);
        }
    }
}
