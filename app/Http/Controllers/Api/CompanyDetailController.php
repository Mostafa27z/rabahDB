<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use App\Helpers\WatermarkHelper;
use Illuminate\Http\Request;

class CompanyDetailController extends Controller
{
    // Get company details (usually one record)
    public function show()
    {
        return response()->json(
            CompanyDetail::first()
        );
    }

    // Create or update company details
    public function store(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $data = $request->validate([
            'phone' => 'nullable|string',
            'mobile' => 'nullable|string',
            'management_email' => 'nullable|email',
            'clients_email' => 'nullable|email',
            'support_email' => 'nullable|email',
            'career_email' => 'nullable|email',
            'linkedin' => 'nullable|string',
            'facebook' => 'nullable|string',
            'twitter' => 'nullable|string',
            'tiktok' => 'nullable|string',
            'instagram' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'snapchat' => 'nullable|string',
            'telegram' => 'nullable|string',
            'first_shift' => 'nullable|string',
            'second_shift' => 'nullable|string',
            'holiday' => 'nullable|string',
            'navbar_logo' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,avif|max:5120',
            'footer_logo' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,avif|max:5120',
            'watermark_image' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,avif|max:5120',
            'watermark_type' => 'nullable|string|in:custom,navbar_logo,footer_logo',
            'watermark_position' => 'nullable|string',
            'watermark_opacity' => 'nullable|integer|min:0|max:100',
            'watermark_size' => 'nullable|integer|min:0|max:100',
        ]);

        $company = CompanyDetail::first();

        if ($request->hasFile('navbar_logo')) {
            if ($company && $company->navbar_logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($company->navbar_logo);
            }
            $file = $request->file('navbar_logo');
            $ext = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'png'));
            $fileName = \Illuminate\Support\Str::random(40) . '.' . $ext;
            $data['navbar_logo'] = $file->storeAs('company', $fileName, 'public');
        } else {
            unset($data['navbar_logo']);
        }

        if ($request->hasFile('footer_logo')) {
            if ($company && $company->footer_logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($company->footer_logo);
            }
            $file = $request->file('footer_logo');
            $ext = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'png'));
            $fileName = \Illuminate\Support\Str::random(40) . '.' . $ext;
            $data['footer_logo'] = $file->storeAs('company', $fileName, 'public');
        } else {
            unset($data['footer_logo']);
        }

        if ($request->hasFile('watermark_image')) {
            if ($company && $company->watermark_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($company->watermark_image);
            }
            $file = $request->file('watermark_image');
            $ext = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'png'));
            $fileName = \Illuminate\Support\Str::random(40) . '.' . $ext;
            $data['watermark_image'] = $file->storeAs('company', $fileName, 'public');
        } else {
            unset($data['watermark_image']);
        }

        // Check if watermark settings are updated
        $settingsChanged = false;
        if ($company) {
            $watermarkKeys = ['navbar_logo', 'footer_logo', 'watermark_image', 'watermark_type', 'watermark_position', 'watermark_opacity', 'watermark_size'];
            foreach ($watermarkKeys as $key) {
                if (array_key_exists($key, $data) && $data[$key] != $company->$key) {
                    $settingsChanged = true;
                    break;
                }
            }
        } else {
            // First time creation - if any watermark settings are provided, trigger regeneration
            $watermarkKeys = ['navbar_logo', 'footer_logo', 'watermark_image', 'watermark_type', 'watermark_position', 'watermark_opacity', 'watermark_size'];
            foreach ($watermarkKeys as $key) {
                if (array_key_exists($key, $data)) {
                    $settingsChanged = true;
                    break;
                }
            }
        }

        $company = CompanyDetail::updateOrCreate(
            ['id' => $company?->id ?? null],
            $data
        );

        if ($settingsChanged) {
            // Trigger watermark regeneration for all property images in background
            WatermarkHelper::regenerateAllInBackground();
        }

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }
}
