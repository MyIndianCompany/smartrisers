<?php

namespace App\Services\User;

use App\Exceptions\CustomException\usernameIsAvailable;
use App\Models\User;
use App\Services\AuthService\AuthServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserService
{
    public function updateProfile(User $user, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $user->id,
//            'username' => 'string|max:255|unique:users,username,' . $user->id,
            'bio' => 'string|max:255',
            'gender' => 'string|in:MALE,FEMALE,CUSTOM',
            'custom_gender' => 'nullable|string|max:255',
            'profile_picture' => 'url|max:2048',
            'url' => 'url|max:255',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first()];
        }

//        $username = strtolower($request->input('username'));
//        if (!$this->isUsernameAvailable($username)) {
//            return ['success' => false, 'error' => 'Username not available'];
//        }

        try {
            DB::beginTransaction();

            $userData = [
                'name' => $request->has('name') ? $request->input('name') : $user->name,
//                'username' => $request->has('username') ? $request->input('username') : $user->username,
                'email' => $request->has('email') ? $request->input('email') : $user->email,
            ];
            $user->update($userData);

            // Update user profile data
            $profileData = [
                'name' => $request->has('name') ? $request->input('name') : $user->name,
//                'username' => $request->has('username') ? $request->input('username') : $user->username,
                'email' => $request->has('email') ? $request->input('email') : $user->email,
                'bio' => $request->has('bio') ? $request->input('bio') : $user->profile->bio,
                'gender' => $request->has('gender') ? $request->input('gender') : $user->profile->gender,
                'custom_gender' => $request->has('custom_gender') ? $request->input('custom_gender') : $user->profile->custom_gender,
                'profile_picture' => $request->has('profile_picture') ? $request->input('profile_picture') : $user->profile->profile_picture,
            ];
            $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData);

            // Update user website data
            $websiteData = [
                'url' => $request->has('url') ? $request->input('url') : $user->website->url,
            ];
            $user->website()->updateOrCreate(['user_id' => $user->id], $websiteData);

            // Commit the transaction
            DB::commit();
            return ['success' => true];
        } catch (\Exception $exception) {
            // Rollback the transaction and report the exception
            DB::rollBack();
            report($exception);
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    // check if that username already exists in the database
    public function isUsernameAvailable($username): bool
    {
        return !User::where('username', $username)->exists();
    }
}
