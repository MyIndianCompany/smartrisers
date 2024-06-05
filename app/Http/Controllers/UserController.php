<?php
namespace App\Http\Controllers;

use App\Common\Constants\Constants;
use App\Http\Requests\UserRequest;
use App\Models\Post;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserWebsiteUrl;
use Carbon\Carbon;
use Cloudinary\Cloudinary;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary as CloudinaryLabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function updateProfile(UserRequest $request)
    {
        try {
            $user = auth()->user();
            DB::beginTransaction();
            if ($request->has('profile_picture')) {
                $file = $request->file('profile_picture');
                if ($file) {
                    $filePath = $file->getRealPath();
                    try {
                        $uploadResult = CloudinaryLabs::upload($filePath)->getSecurePath();
                        $profilePicture = $uploadResult;
                    } catch (\Exception $e) {
                        return response()->json(['message' => 'Cloudinary upload error', 'error' => $e->getMessage()]);
                    }
                } else {
                    return response()->json(['message' => 'No file found in the request.']);
                }
            } else {
                return response()->json(['Request does not contain a profile picture.']);
            }
            $user->update([
                'name' => $request->input('name', $user->name),
                'username' => $request->input('username', $user->username),
                'email' => $request->input('email', $user->email),
                'profile_picture' => $profilePicture,
            ]);
            $userProfile = $user->profile ?: new UserProfile();
            $userProfile->fill([
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'bio' => $request->input('bio', $userProfile->bio),
                'gender' => $request->input('gender', $userProfile->gender),
                'custom_gender' => $request->input('custom_gender', $userProfile->custom_gender),
                'profile_picture' => $profilePicture,
            ]);
            $user->profile()->save($userProfile);
            $urls = $request->input('urls', []);
            $existingUrls = $user->website->pluck('url')->toArray();
            foreach ($urls as $url) {
                if (!in_array($url, $existingUrls) && count($existingUrls) < 5) {
                    $userWebsite = new UserWebsiteUrl(['url' => $url]);
                    $user->website()->save($userWebsite);
                    $existingUrls[] = $url;
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Profile update successful',
                'profile' => $user->profile_picture
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'An error occurred',
                'error' => $exception->getMessage()
            ], 500);
        }
    }





    public function userProfile($username)
    {
        $user = DB::table('users')
            ->where('users.username', $username)
            ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->leftJoin('user_website_urls', 'users.id', '=', 'user_website_urls.user_id')
            ->select(
                'users.id',
                'users.name',
                'users.username',
                'users.email',
                'user_profiles.bio',
                'user_profiles.gender',
                'user_profiles.custom_gender',
                'user_profiles.profile_picture',
                'user_profiles.post_count',
                'user_profiles.follower_count',
                'user_profiles.following_count',
                'user_profiles.created_at',
                'users.status',
                'user_website_urls.url'
            )
            ->get()
            ->groupBy('id');

        if ($user->isEmpty()) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user = $user->map(function ($user) {
            $urls = $user->pluck('url')->toArray();
            unset($user[0]->url); // Remove the first URL from the main object
            $user[0]->urls = $urls; // Add URLs as an array to the main object
            return $user[0];
        });

        return response()->json($user->first());
    }

    public function getAllUserProfile()
    {
        // Retrieve only specified fields from the user_profiles table without related data
        $userProfiles = UserProfile::select(
            'user_profiles.user_id',
            'user_profiles.name',
            'user_profiles.username',
            'user_profiles.email',
            'user_profiles.bio',
            'user_profiles.gender',
            'user_profiles.custom_gender',
            'user_profiles.profile_picture',
            'user_profiles.post_count',
            'user_profiles.follower_count',
            'user_profiles.following_count',
            'users.status'
        )
            ->leftJoin('users', 'user_profiles.user_id', '=', 'users.id')
            ->get();

        // Transform the result set to exclude any unexpected data
        $userProfiles = $userProfiles->map(function ($profile) {
            return [
                'user_id' => $profile->user_id,
                'name' => $profile->name,
                'username' => $profile->username,
                'email' => $profile->email,
                'bio' => $profile->bio,
                'gender' => $profile->gender,
                'custom_gender' => $profile->custom_gender,
                'profile_picture' => $profile->profile_picture,
                'post_count' => $profile->post_count,
                'follower_count' => $profile->follower_count,
                'following_count' => $profile->following_count,
                'status' => $profile->status
            ];
        });

        // Return the transformed profiles
        return $userProfiles;
    }

    public function getFollowersByUsername($username)
    {
        $user = User::where('username', $username)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $followers = $user->followers;
        return response()->json($followers);
    }

    public function getFollowingsByUsername($username)
    {
        $user = User::where('username', $username)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $following = $user->following;
        return response()->json($following);
    }

    public function getUserCounts()
    {
        $userCount = User::has('profile')->count();
        $postCount = Post::has('user')->count();
        return response()->json([
            'total_user_count'  => $userCount,
            'total_post_count'  => $postCount
        ]);
    }

    public function updateStatus(Request $request, User $user)
    {
        try {
            DB::beginTransaction();
            $user->update([
                'status' => $request->has('status') ? $request->input('status') : $user->status
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Status update successfully!',
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Failed to update status!',
                'error' => $exception->getMessage()
            ]);
        }
    }

    public function getNewUsers(Request $request)
    {
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        // Get parameters from the request
        $requestedYear = $request->input('year', null);
        $requestedMonth = $request->input('month', null);
        $requestedWeek = $request->input('week', null);

        // Predefine month names
        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];

        // Predefine last 10 years
        $years = [];
        for ($i = 0; $i < 10; $i++) {
            $years[$currentYear - $i] = $currentYear - $i;
        }

        $response = [];

        // If week is provided, return weekly stats for that week
        if ($requestedWeek) {
            $usersByWeek = User::select(
                DB::raw('WEEK(created_at, 1) as week'),  // Use mode 1 to start week on Monday
                DB::raw('COUNT(*) as count')
            )
                ->where(DB::raw('WEEK(created_at, 1)'), $requestedWeek)
                ->whereYear('created_at', $requestedYear ?? $currentYear)
                ->groupBy('week')
                ->orderBy('week')
                ->get()
                ->keyBy('week')
                ->toArray();

            $response['weekly'] = [
                [
                    'week' => $requestedWeek,
                    'count' => $usersByWeek[$requestedWeek]['count'] ?? 0
                ]
            ];
        }
        // If month is provided, return monthly stats for that month
        else if ($requestedMonth) {
            $usersByMonth = User::select(
                DB::raw('MONTH(created_at) as month_number'),
                DB::raw('COUNT(*) as count')
            )
                ->whereYear('created_at', $requestedYear ?? $currentYear)
                ->whereMonth('created_at', $requestedMonth)
                ->groupBy('month_number')
                ->orderBy('month_number')
                ->get()
                ->keyBy('month_number')
                ->toArray();

            $response['monthly'] = [
                [
                    'month' => $months[$requestedMonth],
                    'count' => $usersByMonth[$requestedMonth]['count'] ?? 0
                ]
            ];
        }
        // If year is provided, return monthly stats for that year
        else if ($requestedYear) {
            $monthlyStats = [];
            foreach ($months as $number => $name) {
                $usersByMonth = User::select(
                    DB::raw('MONTH(created_at) as month_number'),
                    DB::raw('COUNT(*) as count')
                )
                    ->whereYear('created_at', $requestedYear)
                    ->whereMonth('created_at', $number)
                    ->groupBy('month_number')
                    ->orderBy('month_number')
                    ->get()
                    ->keyBy('month_number')
                    ->toArray();

                $monthlyStats[] = [
                    'month' => $name,
                    'count' => $usersByMonth[$number]['count'] ?? 0
                ];
            }
            $response['yearly'] = $monthlyStats;
        }
        // If no specific parameter is provided, return all stats
        else {
            // Monthly stats for the current year
            $usersByMonth = User::select(
                DB::raw('MONTH(created_at) as month_number'),
                DB::raw('COUNT(*) as count')
            )
                ->whereYear('created_at', $currentYear)
                ->groupBy('month_number')
                ->orderBy('month_number')
                ->get()
                ->keyBy('month_number')
                ->toArray();

            // Fill in the missing months with count 0
            $monthlyStats = [];
            foreach ($months as $number => $name) {
                $monthlyStats[] = [
                    'month' => $name,
                    'count' => $usersByMonth[$number]['count'] ?? 0
                ];
            }

            // Yearly stats (last 10 years)
            $usersByYear = User::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('COUNT(*) as count')
            )
                ->whereIn(DB::raw('YEAR(created_at)'), array_values($years))
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get()
                ->keyBy('year')
                ->toArray();

            // Fill in the missing years with count 0
            $yearlyStats = [];
            foreach ($years as $year) {
                $yearlyStats[] = [
                    'year' => $year,
                    'count' => $usersByYear[$year]['count'] ?? 0
                ];
            }

            // Weekly stats for the current month
            $usersByWeek = User::select(
                DB::raw('WEEK(created_at, 1) as week'),  // Use mode 1 to start week on Monday
                DB::raw('COUNT(*) as count')
            )
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->groupBy('week')
                ->orderBy('week')
                ->get()
                ->keyBy('week')
                ->toArray();

            // Get the weeks for the current month
            $weeksInMonth = [];
            $currentDate = Carbon::now()->startOfMonth();
            while ($currentDate->month == $currentMonth) {
                $weeksInMonth[] = $currentDate->weekOfYear;
                $currentDate->addWeek();
            }

            // Fill in the missing weeks with count 0
            $weeklyStats = [];
            foreach ($weeksInMonth as $week) {
                $weeklyStats[] = [
                    'week' => $week,
                    'count' => $usersByWeek[$week]['count'] ?? 0
                ];
            }

            $response = [
                'monthly' => $monthlyStats,
                'yearly' => $yearlyStats,
                'weekly' => $weeklyStats,
            ];
        }

        return response()->json($response);
    }
}

