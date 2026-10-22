<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CareerController extends Controller
{
    /**
     * Store career application (Join Us form)
     */
    public function store(Request $request)
    {
        $request->validate([
            'full_name'  => 'required|string|max:255',
            'phone'      => 'required|string|max:50',
            'email'      => 'required|email|max:255',
            'experience' => 'nullable|integer|min:0',

            'cv'    => 'required|file|mimes:pdf,doc,docx|max:10240',
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',

            'linkedin'  => 'nullable|url|max:500',
            'facebook'  => 'nullable|url|max:500',
            'instagram' => 'nullable|url|max:500',
            'twitter'   => 'nullable|url|max:500',
            'tiktok'    => 'nullable|url|max:500',

            'reason' => 'required|string|max:3000',
        ]);

        $cvPath = null;
        if ($request->hasFile('cv')) {
            $cvPath = $request->file('cv')->store('careers/cvs', 'public');
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('careers/photos', 'public');
        }

        $application = JobApplication::create([
            'full_name'  => $request->input('full_name'),
            'phone'      => $request->input('phone'),
            'email'      => $request->input('email'),
            'experience' => $request->input('experience', 0),
            'cv_path'    => $cvPath,
            'photo_path' => $photoPath,
            'linkedin'   => $request->input('linkedin'),
            'facebook'   => $request->input('facebook'),
            'instagram'  => $request->input('instagram'),
            'twitter'    => $request->input('twitter'),
            'tiktok'     => $request->input('tiktok'),
            'reason'     => $request->input('reason'),
            'status'     => 'pending',
        ]);

        Log::info('New career application received', [
            'application_id' => $application->id,
            'full_name'      => $application->full_name,
            'email'          => $application->email,
        ]);

        return response()->json([
            'success' => true,
            'item_id' => $application->id,
            'message' => 'تم استلام طلب التوظيف بنجاح وسيتم التواصل معك بعد مراجعة السيرة الذاتية',
        ], 201);
    }
}
