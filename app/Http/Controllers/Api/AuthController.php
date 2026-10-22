<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\PasswordResetOtp;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    /**
     * REGISTER
     */
    public function register(Request $request)
{
    $request->validate([
        'name'           => 'required|string|max:255',
        'email'          => 'required|email|unique:users',
        'password'       => ['required', Password::min(8)],
        'phone'          => 'nullable|string|max:20',
        'instagram_link' => 'nullable|url|max:255',
        'tiktok_link'    => 'nullable|url|max:255',
        'whatsapp_link'  => 'nullable|url|max:255',
        'facebook_link'  => 'nullable|url|max:255',
        'linkedin_link'  => 'nullable|url|max:255',
        'twitter_link'   => 'nullable|url|max:255',
        'snapchat_link'  => 'nullable|url|max:255',
        'telegram_link'  => 'nullable|string|max:255',
        'bio'            => 'nullable|string',
        'image'          => 'nullable|image|max:5120',
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('users', 'public');
    }

    $user = User::create([
        'name'           => $request->name,
        'email'          => $request->email,
        'password'       => Hash::make($request->password),
        'role'           => 'client', // force role
        'phone'          => $request->phone,
        'instagram_link' => $request->instagram_link,
        'tiktok_link'    => $request->tiktok_link,
        'whatsapp_link'  => $request->whatsapp_link,
        'facebook_link'  => $request->facebook_link,
        'linkedin_link'  => $request->linkedin_link,
        'twitter_link'   => $request->twitter_link,
        'snapchat_link'  => $request->snapchat_link,
        'telegram_link'  => $request->telegram_link,
        'bio'            => $request->bio,
        'team_lead_id'   => null,
        'image'          => $imagePath,
    ]);

    // Send email verification
    $user->sendEmailVerificationNotification();

    return response()->json([
        'message' => 'Account created. Please verify your email.',
        'user' => $user,
    ], 201);
}


    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Standard password check
        $isValidPassword = Hash::check($request->password, $user->password);
        
        // Phone number check for clients
        $isPhoneLogin = $user->role === 'client' && $user->phone && $request->password === $user->phone;

        if (! $isValidPassword && ! $isPhoneLogin) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email before login'
            ], 403);
        }


        $token = $user->createToken('crm_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
    public function sendOtp(Request $request)
{
    $request->validate(['email' => 'required|email|exists:users,email']);

    $otp = rand(100000, 999999);

    PasswordResetOtp::updateOrCreate(
        ['email' => $request->email],
        [
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(10)
        ]
    );

    Mail::to($request->email)->send(new SendOtpMail($otp));

    return response()->json(['message' => 'OTP sent to email']);
}
public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required'
    ]);

    $record = PasswordResetOtp::where('email', $request->email)
        ->where('otp', $request->otp)
        ->first();

    if (! $record) {
        return response()->json(['message' => 'Invalid OTP'], 400);
    }

    if ($record->expires_at < now()) {
        return response()->json(['message' => 'OTP expired'], 400);
    }

    return response()->json(['message' => 'OTP verified']);
}
public function resetPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required',
        'password' => ['required', Password::min(8)]
    ]);

    $record = PasswordResetOtp::where('email', $request->email)
        ->where('otp', $request->otp)
        ->first();

    if (! $record || $record->expires_at < now()) {
        return response()->json(['message' => 'Invalid or expired OTP'], 400);
    }

    User::where('email', $request->email)
        ->update(['password' => Hash::make($request->password)]);

    $record->delete(); // prevent reuse

    return response()->json(['message' => 'Password reset successful']);
}

    public function verifyEmail(Request $request, $id, $hash)
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://tamleekuae.com')), '/');

        $user = User::find($id);

        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'User not found'], 404);
            }
            return redirect($frontendUrl . '/verify-email?status=error');
        }

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid verification link'], 403);
            }
            return redirect($frontendUrl . '/verify-email?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Email already verified']);
            }
            return redirect($frontendUrl . '/verify-email?status=already_verified');
        }

        $user->markEmailAsVerified();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Email verified successfully']);
        }

        return redirect($frontendUrl . '/verify-email?status=success');
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        // Load relationships if needed
        if ($user->isTeamLead()) {
            $user->load('agents');
        }
        
        // Append average rating
        $user->average_rating = $user->averageRating();
        
        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * Update user profile (self)
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Get authenticated user

        $request->validate([
            'name'           => 'sometimes|string|max:255',
            'email'          => 'sometimes|email|unique:users,email,' . $user->id,
            'password'       => ['nullable', Password::min(8)],
            'phone'          => 'nullable|string|max:20',
            'username'       => 'sometimes|string|alpha_dash|max:255|unique:users,username,' . $user->id,
            'instagram_link' => 'nullable|url|max:255',
            'tiktok_link'    => 'nullable|url|max:255',
            'whatsapp_link'  => 'nullable|url|max:255',
            'facebook_link'  => 'nullable|url|max:255',
            'linkedin_link'  => 'nullable|url|max:255',
            'twitter_link'   => 'nullable|url|max:255',
            'snapchat_link'  => 'nullable|url|max:255',
            'telegram_link'  => 'nullable|string|max:255',
            'bio'            => 'nullable|string',
            'image'          => 'nullable|image|max:5120',
        ]);

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('phone')) $user->phone = $request->phone;
        if ($request->has('username')) $user->username = $request->username;
        
        // Social links
        if ($request->has('instagram_link')) $user->instagram_link = $request->instagram_link;
        if ($request->has('tiktok_link')) $user->tiktok_link = $request->tiktok_link;
        if ($request->has('whatsapp_link')) $user->whatsapp_link = $request->whatsapp_link;
        if ($request->has('facebook_link')) $user->facebook_link = $request->facebook_link;
        if ($request->has('linkedin_link')) $user->linkedin_link = $request->linkedin_link;
        if ($request->has('twitter_link')) $user->twitter_link = $request->twitter_link;
        if ($request->has('snapchat_link')) $user->snapchat_link = $request->snapchat_link;
        if ($request->has('telegram_link')) $user->telegram_link = $request->telegram_link;
        if ($request->has('bio')) $user->bio = $request->bio;

        // Image handling
        if ($request->hasFile('image')) {
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $user->image = $request->file('image')->store('users', 'public');
        }

        // Explicitly DO NOT update role here.

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

}
