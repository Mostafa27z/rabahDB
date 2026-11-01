<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CareerController;
// Career (Join Us form) - Throttled to avoid abuse
Route::post('/career/apply', [CareerController::class, 'store'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

use App\Http\Controllers\Api\AdController;

// Public ad routes (Local DB)
Route::get('/ads/active', [AdController::class, 'active']);
Route::get('/ads/{id}', [AdController::class, 'show']);

// Admin-only ad management (Local DB)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/ads', [AdController::class, 'index']);
    Route::post('/ads', [AdController::class, 'store']);
    Route::match(['put', 'post'], '/ads/{id}', [AdController::class, 'update']);
    Route::patch('/ads/{id}/toggle', [AdController::class, 'toggleStatus']);
    Route::delete('/ads/{id}', [AdController::class, 'destroy']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::get('/me', fn () => auth()->user());
});
Route::post('forgot-password', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1');
Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Models\User;
use Illuminate\Support\Facades\URL;

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed'])->name('verification.verify');


Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'Verification link sent']);
})->middleware(['auth:sanctum']);

use App\Http\Controllers\Api\PropertyManagementController;
use App\Http\Controllers\Api\DashboardStatsController;

// Public property routes (Fast Local DB)
Route::post('/properties/submit', [PropertyManagementController::class, 'clientSubmit'])->middleware('throttle:10,1');
Route::get('/properties/public', [PropertyManagementController::class, 'index']);
Route::get('/properties/home', [PropertyManagementController::class, 'homeProperties']);
Route::get('/properties/featured-count', [PropertyManagementController::class, 'featuredCount']);
Route::get('/properties/types', [PropertyManagementController::class, 'getTypes']);
Route::get('/properties/{id}', [PropertyManagementController::class, 'show'])->whereNumber('id');

// Dashboard Analytics & Stats
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard/stats', [DashboardStatsController::class, 'index']);

    // Saved Views
    Route::get('/property-views', [PropertyManagementController::class, 'getViews']);
    Route::post('/property-views', [PropertyManagementController::class, 'storeView']);
    Route::delete('/property-views/{id}', [PropertyManagementController::class, 'destroyView']);

    // Property Management (Fast Local DB)
    Route::get('/properties', [PropertyManagementController::class, 'index']);
    Route::post('/properties', [PropertyManagementController::class, 'store']);
    Route::patch('/properties/{id}/toggle-featured', [PropertyManagementController::class, 'toggleFeatured'])->whereNumber('id');
    Route::match(['put', 'post'], '/properties/{id}', [PropertyManagementController::class, 'update'])->whereNumber('id');
    Route::delete('/properties/{id}', [PropertyManagementController::class, 'destroy'])->whereNumber('id');
    Route::post('/properties/{id}/request-promotion', [PropertyManagementController::class, 'requestPromotion'])->whereNumber('id');
    Route::post('/properties/{id}/submit-promotion', [PropertyManagementController::class, 'submitPromotion'])->whereNumber('id');
    Route::post('/properties/{id}/cancel-promotion', [PropertyManagementController::class, 'cancelPromotion'])->whereNumber('id');

    // Client Property Submissions ("طلبات عرض العقارات")
    Route::get('/property-submissions', [PropertyManagementController::class, 'indexSubmissions']);
    Route::match(['patch', 'put'], '/property-submissions/{id}', [PropertyManagementController::class, 'updateSubmission'])->whereNumber('id');
    Route::delete('/property-submissions/{id}', [PropertyManagementController::class, 'destroySubmission'])->whereNumber('id');
    Route::post('/property-submissions/{id}/convert', [PropertyManagementController::class, 'convertSubmissionToProperty'])->whereNumber('id');
});

// Public: get all real estates (Mapped to local DB for instant speed)
Route::get('/real-estates/all', [PropertyManagementController::class, 'index']);
Route::get('/real-estates/types', [PropertyManagementController::class, 'getTypes']);
Route::get('/real-estates/portfolio/{userId}', [PropertyManagementController::class, 'portfolio']);
Route::get('/real-estates/enums', [PropertyManagementController::class, 'getTypes']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/real-estates', [PropertyManagementController::class, 'index']);
    Route::post('/real-estates', [PropertyManagementController::class, 'store']);
    Route::post('/real-estates/{id}/bookmark', [PropertyManagementController::class, 'toggleBookmark']);
    Route::match(['put', 'post'], '/real-estates/{id}', [PropertyManagementController::class, 'update']);
    Route::delete('/real-estates/{id}', [PropertyManagementController::class, 'destroy']);
});
Route::get('/real-estates/{id}', [PropertyManagementController::class, 'show']);

use App\Http\Controllers\Api\UserManagementController;
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::get('/designers', [UserManagementController::class, 'getDesigners']);
    Route::post('/users', [UserManagementController::class, 'store']);
    Route::get('/users/{id}', [UserManagementController::class, 'show']);
    Route::put('/users/{id}', [UserManagementController::class, 'update']);
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
    Route::post('/users/{id}/rate', [UserManagementController::class, 'rateUser']);
    Route::get('/users/{id}/rate/check', [UserManagementController::class, 'getRatedByUser']);
    Route::post('/users/{id}/resend-verification', [UserManagementController::class, 'resendVerification']);
    
    // Admin-only rating management
    Route::get('/ratings', [UserManagementController::class, 'getAllRatings']);
    Route::patch('/ratings/{id}/status', [UserManagementController::class, 'updateRatingStatus']);
    Route::delete('/ratings/{id}', [UserManagementController::class, 'deleteRating']);
});
use App\Http\Controllers\Api\CommunicationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/communication/comment', [CommunicationController::class, 'addComment']);
    Route::get('/leads', [CommunicationController::class, 'indexLeads']);
    Route::patch('/leads/{id}', [CommunicationController::class, 'updateLead']);
    Route::put('/leads/{id}', [CommunicationController::class, 'updateLead']);
    Route::delete('/leads/{id}', [CommunicationController::class, 'destroyLead']);
});

Route::post('/communication/lead', [CommunicationController::class, 'storeLead'])->middleware('throttle:10,1');
Route::post('/communication/contact', [CommunicationController::class, 'storeContact'])->middleware('throttle:10,1');

use App\Http\Controllers\Api\CompanyDetailController;

// Public
Route::get('/company-details', [CompanyDetailController::class, 'show']);

// Protected (Admin Only)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/company-details', [CompanyDetailController::class, 'store']);
});


