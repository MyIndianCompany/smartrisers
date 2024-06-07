<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use App\Models\UserReportFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserReportController extends Controller
{
    public function store(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'reported_user_id' => 'exists:users,id',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,txt|max:2048' // Adjust mime types and size limit as needed
        ]);

        try {
            DB::beginTransaction();
            $reporter = auth()->user()->id;
            // Create the user report
            $userReport = UserReport::create([
                'reporter_id' => $reporter,
                'reported_user_id' => $request->reported_user_id,
                'report_description' => $request->report_description
            ]);

            // Handle file uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    // Store the file
                    $filePath = $file->store('report_files');

                    // Save the file details in UserReportFile model
                    UserReportFile::create([
                        'user_report_id' => $userReport->id,
                        'original_file_name' => $file->getClientOriginalName(),
                        'files' => $filePath,
                        'mime_type' => $file->getClientMimeType()
                    ]);
                }
            }
            DB::commit();
            // Return a response
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
}
