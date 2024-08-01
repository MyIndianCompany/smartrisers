<?php

namespace App\Http\Controllers;

use App\Models\WebHelpEnquiry;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebHelpEnquiryController extends Controller
{
    public function index()
    {
        return WebHelpEnquiry::select('email', 'message', 'created_at')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'message' => 'required'
        ]);
        try {
            DB::beginTransaction();
            WebHelpEnquiry::create([
                'email' => $request->input('email'),
                'message' => $request->input('message')
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Message has been sent!'
            ], 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Unable to sent message. Please try again later'
            ], 500);
        }
    }
}
