<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use App\Models\UserReportFile;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary as CloudinaryLabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserReportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = UserReport::with([
            'reportFiles',
            'reporter:id,name,username,email,profile_picture',
            'reported:id,name,username,email,profile_picture'
        ])
            ->when($status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($startDate, function ($query, $startDate) {
                return $query->whereDate('created_at', '>=', $startDate);
            })
            ->when($endDate, function ($query, $endDate) {
                return $query->whereDate('created_at', '<=', $endDate);
            })
            ->orderBy('id', 'DESC')
            ->get();

        return response()->json($report);
    }


    public function store(Request $request)
    {
        $request->validate([
            'reported_user_id' => 'exists:users,id',
            'report_description' => 'required',
            'files.*' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,txt|max:2048'
        ]);

        try {
            DB::beginTransaction();
            $reporter = auth()->user()->id;
            if ($reporter == $request->reported_user_id) {
                return response()->json([
                    'message' => "Unable to report yourself"
                ], 422);
            }
            $userReport = UserReport::create([
                'reporter_id' => $reporter,
                'reported_user_id' => $request->reported_user_id,
                'report_description' => $request->report_description
            ]);
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = $file->store('user_reports', 'public');;
                    $url = $$url = Storage::disk('public')->url($filePath);
                    UserReportFile::create([
                        'user_report_id' => $userReport->id,
                        'original_file_name' => $file->getClientOriginalName(),
                        'files' => $url,
                        'mime_type' => $file->getClientMimeType()
                    ]);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Report created successfully'], 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Unable to create your report!',
                'error' => $exception->getMessage()
            ], 422);
        }
    }

    public function updateReportStatus(UserReport $userReport, Request $request)
    {
        // Validate the status input
        $validated = $request->validate([
            'status' => 'required|in:completed,pending'
        ]);

        // Update the report status
        $userReport->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => 'Report status successfully updated'
        ]);
    }
}
