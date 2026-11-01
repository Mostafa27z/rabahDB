<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRating;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserManagementController extends Controller
{
    /**
     * List users with search & pagination
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = User::query();

        // Role & Type-based visibility
        $type = $request->input('type'); // 'team', 'staff', 'clients', 'designers'
        $role = $request->input('role');

        if ($type === 'team' || $type === 'all_staff') {
            // Accessible to all authenticated staff to populate team filters & dropdowns
            $teamMembers = User::whereIn('role', ['admin', 'team_lead', 'agent', 'designer'])
                ->select('id', 'name', 'email', 'role', 'image')
                ->withCount('properties')
                ->orderByDesc('properties_count')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $teamMembers,
            ]);
        }

        if ($role === 'designer' || $type === 'designers') {
            $query->where('role', 'designer');
        } elseif ($type === 'staff') {
            if ($user && $user->isTeamLead()) {
                $query->where('role', 'agent')->where('team_lead_id', $user->id);
            } else {
                $query->whereIn('role', ['admin', 'team_lead', 'agent', 'designer']);
            }
        } elseif ($type === 'clients') {
            $query->where('role', 'client');
        } elseif ($user->isTeamLead()) {
            $query->where('role', 'agent')
                  ->where('team_lead_id', $user->id);
        } elseif ($user->isAgent()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Specific role filter if provided
        if ($role && $role !== 'designer') {
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('username', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('instagram_link', 'like', "%$search%")
                  ->orWhere('tiktok_link', 'like', "%$search%")
                  ->orWhere('whatsapp_link', 'like', "%$search%")
                  ->orWhere('facebook_link', 'like', "%$search%")
                  ->orWhere('linkedin_link', 'like', "%$search%")
                  ->orWhere('twitter_link', 'like', "%$search%")
                  ->orWhere('snapchat_link', 'like', "%$search%")
                  ->orWhere('telegram_link', 'like', "%$search%");
            });
        }

        // Pagination
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $total = $query->count();

        $query->with(['teamLead:id,name,email,username,phone,image'])
              ->withCount('properties')
              ->withCount(['ratingsReceived as total_ratings' => function($q) {
                  $q->where('status', 'public');
              }])
              ->withAvg(['ratingsReceived as average_rating' => function($q) {
                  $q->where('status', 'public');
              }], 'rating');

        // Sorting (endpoint-powered)
        $sortBy = $request->input('sort_by');
        if ($sortBy === 'highest_rating') {
            $query->orderByRaw('average_rating IS NULL, average_rating DESC');
        } elseif ($sortBy === 'most_viewed') {
            $query->orderByDesc('portfolio_views');
        } elseif ($sortBy === 'most_properties') {
            $query->orderByDesc('properties_count');
        } else {
            $query->latest();
        }

        $users = $query->skip(($page - 1) * $perPage)
                       ->take($perPage)
                       ->get();

        // Include portfolio_views only for admins or team leads (viewing their agents)
        $usersData = $users->map(function($targetUser) use ($user) {
            $userData = $targetUser->toArray();
            
            // Format rating accurately (0.0 if unrated)
            $userData['average_rating'] = $targetUser->average_rating ? round((float)$targetUser->average_rating, 1) : 0;
            $userData['total_ratings'] = (int)($targetUser->total_ratings ?? 0);
            $userData['properties_count'] = (int)($targetUser->properties_count ?? 0);

            // Format team_lead info cleanly
            if ($targetUser->teamLead) {
                $userData['team_lead'] = [
                    'id' => $targetUser->teamLead->id,
                    'name' => $targetUser->teamLead->name,
                    'email' => $targetUser->teamLead->email,
                    'username' => $targetUser->teamLead->username,
                ];
            } else {
                $userData['team_lead'] = null;
            }

            // Only include portfolio_views for admins or team leads viewing their agents
            if ($user->isAdmin() || ($user->isTeamLead() && $targetUser->team_lead_id === $user->id)) {
                $userData['portfolio_views'] = $targetUser->portfolio_views;
            } else {
                // Remove portfolio_views if it was included in toArray()
                unset($userData['portfolio_views']);
            }

            // Include agents_count for team leads (admin view)
            if ($targetUser->role === 'team_lead' && $user->isAdmin()) {
                $userData['agents_count'] = User::where('role', 'agent')
                    ->where('team_lead_id', $targetUser->id)
                    ->count();
            }
            
            return $userData;
        });

        // Top statistical counters
        $stats = [
            'staff' => [
                'admins_count' => User::where('role', 'admin')->count(),
                'team_leads_count' => User::where('role', 'team_lead')->count(),
                'agents_count' => User::where('role', 'agent')->count(),
                'total_staff' => User::whereIn('role', ['admin', 'team_lead', 'agent'])->count(),
            ],
            'clients' => [
                'total_clients' => User::where('role', 'client')->count(),
                'verified_clients' => User::where('role', 'client')->whereNotNull('email_verified_at')->count(),
                'new_this_month' => User::where('role', 'client')->where('created_at', '>=', now()->startOfMonth())->count(),
            ]
        ];

        return response()->json([
            'data' => $usersData,
            'total' => $total,
            'page' => (int)$page,
            'per_page' => (int)$perPage,
            'stats' => $stats,
        ]);
    }

    /**
     * Create user
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email'=> 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:client,agent,team_lead,admin,designer',
            'phone' => 'nullable|string|max:20',
            'username' => 'nullable|string|alpha_dash|max:255|unique:users,username',
            'instagram_link' => 'nullable|url|max:255',
            'tiktok_link' => 'nullable|url|max:255',
            'whatsapp_link' => 'nullable|url|max:255',
            'facebook_link' => 'nullable|url|max:255',
            'linkedin_link' => 'nullable|url|max:255',
            'twitter_link' => 'nullable|url|max:255',
            'snapchat_link' => 'nullable|url|max:255',
            'telegram_link' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
        ]);

        // Role-based creation rules
        if ($user->isAdmin()) {
            // Admin can create anyone
        } elseif ($user->isTeamLead()) {
            if ($request->role !== 'agent') {
                return response()->json([
                    'message' => 'Team lead can only create agents'
                ], 403);
            }
            $request->merge(['team_lead_id' => $user->id]);
        } else {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Auto-assign hierarchy for agents and clients
        if ($request->role === 'agent' && $user->isTeamLead()) {
            $request->merge(['team_lead_id' => $user->id]);
        }
        if ($request->role === 'client' && $user->isAgent()) {
            $request->merge([
                'team_lead_id' => $user->team_lead_id
            ]);
        }

        $username = $request->username;
        if (empty($username)) {
            $emailPrefix = explode('@', $request->email)[0];
            $baseUsername = \Illuminate\Support\Str::slug($emailPrefix ?: $request->name);
            if (empty($baseUsername)) {
                $baseUsername = 'user';
            }
            $username = $baseUsername;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . '-' . $counter;
                $counter++;
            }
        }

        $newUser = User::create([
            'name' => $request->name,
            'email'=> $request->email,
            'password'=> Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role,
            'username' => $username,
            'team_lead_id' => $request->team_lead_id ?: null,
            'instagram_link' => $request->instagram_link ?? null,
            'tiktok_link' => $request->tiktok_link ?? null,
            'whatsapp_link' => $request->whatsapp_link ?? null,
            'facebook_link' => $request->facebook_link ?? null,
            'linkedin_link' => $request->linkedin_link ?? null,
            'twitter_link' => $request->twitter_link ?? null,
            'snapchat_link' => $request->snapchat_link ?? null,
            'telegram_link' => $request->telegram_link ?? null,
            'bio' => $request->bio ?? null,
            'image' => $request->hasFile('image') ? $request->file('image')->store('users', 'public') : null,
        ]);

        // Send email verification notification to new user
        try {
            $newUser->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::error("UserManagementController: Failed to send email verification notification to {$newUser->email}: " . $e->getMessage());
        }

        return response()->json($newUser, 201);
    }

    /**
     * Show user
     */
    public function show($id)
    {
        $user = auth()->user();
        $target = User::findOrFail($id);

        if ($user->isTeamLead() && $target->role !== 'agent') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($user->isTeamLead() && $target->team_lead_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($user->isAgent()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Load ratings with rater information
        // Admins and the rated user see all their ratings (including private), others only see public ratings
        $ratingsQuery = $target->ratingsReceived()->with('rater:id,name,email,role,image');
        
        if (!$user->isAdmin() && $user->id !== $target->id) {
            // If not admin and not viewing own profile, only show public ratings
            $ratingsQuery->where('status', 'public');
        } elseif ($user->id === $target->id) {
            // If viewing own profile, show all ratings except hidden ones
            $ratingsQuery->whereIn('status', ['public', 'private']);
        }
        // Admins see all ratings (no filter)
        
        $ratings = $ratingsQuery->get();
        
        // Format ratings
        $formattedRatings = $ratings->map(function($rating) use ($user, $target) {
            $ratingData = [
                'id' => $rating->id,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at,
                'rater' => [
                    'id' => $rating->rater->id,
                    'name' => $rating->rater->name,
                    'email' => $rating->rater->email,
                    'role' => $rating->rater->role,
                    'image' => $rating->rater->image ? asset('storage/' . $rating->rater->image) : null,
                ],
            ];
            
            // Include status for admins and the rated user
            if ($user->isAdmin() || $user->id === $target->id) {
                $ratingData['status'] = $rating->status;
            }
            
            return $ratingData;
        });

        // Calculate average rating (only from public ratings)
        $averageRating = $target->ratingsReceived()->where('status', 'public')->avg('rating');

        $userData = $target->toArray();
        $userData['ratings'] = $formattedRatings;
        $userData['average_rating'] = $averageRating ? round($averageRating, 2) : null;
        $userData['total_ratings'] = $target->ratingsReceived()->where('status', 'public')->count();

        // Include portfolio_views only for admins or team leads viewing their agents
        if ($user->isAdmin() || ($user->isTeamLead() && $target->team_lead_id === $user->id)) {
            $userData['portfolio_views'] = $target->portfolio_views;
        }

        // Role-specific detailed team hierarchy and property statistics
        if ($target->role === 'team_lead') {
            $agentIds = $target->agents()->pluck('id')->push($target->id);
            $teamProps = Property::whereIn('user_id', $agentIds);
            
            $userData['properties_stats'] = [
                'total' => (clone $teamProps)->count(),
                'available' => (clone $teamProps)->whereIn('status', ['available', 'new'])->count(),
                'sold' => (clone $teamProps)->where('status', 'sold')->count(),
                'rented' => (clone $teamProps)->where('status', 'rented')->count(),
            ];

            $userData['team_agents'] = $target->agents()
                ->select('id', 'name', 'email', 'phone', 'image', 'role')
                ->withCount('properties')
                ->get();
        } elseif ($target->role === 'agent') {
            $agentProps = $target->properties();
            $userData['properties_stats'] = [
                'total' => (clone $agentProps)->count(),
                'available' => (clone $agentProps)->whereIn('status', ['available', 'new'])->count(),
                'sold' => (clone $agentProps)->where('status', 'sold')->count(),
                'rented' => (clone $agentProps)->where('status', 'rented')->count(),
            ];

            if ($target->teamLead) {
                $userData['team_lead'] = [
                    'id' => $target->teamLead->id,
                    'name' => $target->teamLead->name,
                    'email' => $target->teamLead->email,
                    'phone' => $target->teamLead->phone,
                    'image' => $target->teamLead->image,
                ];
            }

            if ($target->team_lead_id) {
                $userData['team_colleagues'] = User::where('team_lead_id', $target->team_lead_id)
                    ->where('id', '!=', $target->id)
                    ->select('id', 'name', 'email', 'phone', 'image')
                    ->withCount('properties')
                    ->get();
            } else {
                $userData['team_colleagues'] = [];
            }
        } elseif ($target->role === 'admin') {
            $userData['properties_stats'] = [
                'total' => Property::count(),
                'available' => Property::whereIn('status', ['available', 'new'])->count(),
                'sold' => Property::where('status', 'sold')->count(),
                'rented' => Property::where('status', 'rented')->count(),
            ];
            $userData['system_stats'] = [
                'total_teams' => User::where('role', 'team_lead')->count(),
                'total_agents' => User::where('role', 'agent')->count(),
                'total_staff' => User::whereIn('role', ['admin', 'team_lead', 'agent'])->count(),
            ];
        }

        return response()->json($userData);
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $target = User::findOrFail($id);

        // Permission checks for update (Deny by default)
        if ($user->isAdmin()) {
            // Admin can update any user
        } elseif ($user->isTeamLead()) {
            if ($target->role !== 'agent' || $target->team_lead_id !== $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } elseif ($user->id === $target->id) {
            // User can update their own profile (cannot change role or team lead)
        } else {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email'=> 'sometimes|email|unique:users,email,'.$target->id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:client,agent,team_lead,admin,designer',
            'phone' => 'nullable|string|max:20',
            'username' => 'sometimes|string|alpha_dash|max:255|unique:users,username,'.$target->id,
            'instagram_link' => 'nullable|url|max:255',
            'tiktok_link' => 'nullable|url|max:255',
            'whatsapp_link' => 'nullable|url|max:255',
            'facebook_link' => 'nullable|url|max:255',
            'linkedin_link' => 'nullable|url|max:255',
            'twitter_link' => 'nullable|url|max:255',
            'snapchat_link' => 'nullable|url|max:255',
            'telegram_link' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
        ]);

        if ($request->has('password')) {
            $target->password = Hash::make($request->password);
        }

        if ($request->has('name')) $target->name = $request->name;
        if ($request->has('email')) $target->email = $request->email;
        if ($request->has('phone')) $target->phone = $request->phone;
        if ($request->has('username')) $target->username = $request->username;
        if ($request->has('role') && $user->isAdmin()) $target->role = $request->role;
        if ($request->has('team_lead_id') && $user->isAdmin()) $target->team_lead_id = $request->team_lead_id ?: null;
        if ($request->has('instagram_link')) $target->instagram_link = $request->instagram_link;
        if ($request->has('tiktok_link')) $target->tiktok_link = $request->tiktok_link;
        if ($request->has('whatsapp_link')) $target->whatsapp_link = $request->whatsapp_link;
        if ($request->has('facebook_link')) $target->facebook_link = $request->facebook_link;
        if ($request->has('linkedin_link')) $target->linkedin_link = $request->linkedin_link;
        if ($request->has('twitter_link')) $target->twitter_link = $request->twitter_link;
        if ($request->has('snapchat_link')) $target->snapchat_link = $request->snapchat_link;
        if ($request->has('telegram_link')) $target->telegram_link = $request->telegram_link;
        if ($request->has('bio')) $target->bio = $request->bio;

        if ($request->hasFile('image')) {
            if ($target->image) {
                Storage::disk('public')->delete($target->image);
            }
            $target->image = $request->file('image')->store('users', 'public');
        }

        $target->save();

        return response()->json($target);
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $target = User::findOrFail($id);

        // Permission checks (Deny by default)
        if ($user->isAdmin()) {
            // Admin can delete users
        } elseif ($user->isTeamLead()) {
            if ($target->role !== 'agent' || $target->team_lead_id !== $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $target->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    /**
     * Rate a user (any authenticated user can rate)
     */
    public function rateUser(Request $request, $id)
    {
        $user = auth()->user();
        $target = User::findOrFail($id);

        // Cannot rate yourself
        if ($user->id === $target->id) {
            return response()->json(['message' => 'You cannot rate yourself'], 403);
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Create or update rating
        $rating = UserRating::updateOrCreate(
            [
                'user_id' => $target->id,
                'rater_id' => $user->id,
            ],
            [
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'private', // Reset to private on update
            ]
        );

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating' => [
                'id' => $rating->id,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at,
                'updated_at' => $rating->updated_at,
            ]
        ], 201);
    }

    /**
     * Update rating status (Admin or rated user)
     * Admin can set: public, private, hidden
     * Rated user can set: public, private (cannot hide, only admin can hide)
     */
    public function updateRatingStatus(Request $request, $ratingId)
    {
        $user = auth()->user();
        $rating = UserRating::findOrFail($ratingId);

        // Check permissions: Admin or the user who was rated
        $isAdmin = $user->isAdmin();
        $isRatedUser = $user->id === $rating->user_id;

        if (!$isAdmin && !$isRatedUser) {
            return response()->json(['message' => 'Forbidden - You can only manage ratings on your own profile'], 403);
        }

        // Validate status based on user role
        if ($isAdmin) {
            $request->validate([
                'status' => 'required|in:public,private,hidden',
            ]);
        } else {
            // Rated users can only set public or private, not hidden
            $request->validate([
                'status' => 'required|in:public,private',
            ]);
        }

        $rating->status = $request->status;
        $rating->save();

        return response()->json([
            'message' => 'Rating status updated successfully',
            'rating' => [
                'id' => $rating->id,
                'status' => $rating->status,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'user_id' => $rating->user_id,
                'rater_id' => $rating->rater_id,
            ]
        ]);
    }

    /**
     * Delete a rating (Admin only)
     */
    public function deleteRating($ratingId)
    {
        $user = auth()->user();

        // Only admins can delete ratings
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $rating = UserRating::findOrFail($ratingId);
        $rating->delete();

        return response()->json([
            'message' => 'Rating deleted successfully'
        ]);
    }

    /**
     * Get all ratings for admin management (Admin only)
     */
    public function getAllRatings(Request $request)
    {
        $user = auth()->user();

        // Only admins can view all ratings
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $status = $request->input('status'); // Filter by status if provided

        $query = UserRating::with(['user:id,name,email,role', 'rater:id,name,email,role']);

        if ($status && in_array($status, ['public', 'private', 'hidden'])) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $ratings = $query->skip(($page - 1) * $perPage)
                        ->take($perPage)
                        ->orderBy('created_at', 'desc')
                        ->get();

        $formattedRatings = $ratings->map(function($rating) {
            return [
                'id' => $rating->id,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'status' => $rating->status,
                'created_at' => $rating->created_at,
                'user' => [
                    'id' => $rating->user->id,
                    'name' => $rating->user->name,
                    'email' => $rating->user->email,
                    'role' => $rating->user->role,
                ],
                'rater' => [
                    'id' => $rating->rater->id,
                    'name' => $rating->rater->name,
                    'email' => $rating->rater->email,
                    'role' => $rating->rater->role,
                ],
            ];
        });

        return response()->json([
            'data' => $formattedRatings,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
    /**
     * Get rating by authenticated user for a specific target user
     */
    public function getRatedByUser(Request $request, $id)
    {
        $user = auth()->user();
        $targetId = $id;

        $rating = UserRating::where('rater_id', $user->id)
                            ->where('user_id', $targetId)
                            ->first();

        if ($rating) {
            return response()->json([
                'id' => $rating->id,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at,
                'updated_at' => $rating->updated_at,
            ]);
        }

        return response()->json(false);
    }

    /**
     * Get all active designers for selection in property wizards and campaigns
     */
    public function getDesigners(Request $request)
    {
        $designers = User::where('role', 'designer')
            ->select('id', 'name', 'email', 'image', 'phone')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $designers,
        ]);
    }

    /**
     * Resend verification email for a user
     */
    public function resendVerification($id)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isTeamLead()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $target = User::findOrFail($id);

        if ($target->hasVerifiedEmail()) {
            return response()->json(['message' => 'البريد الإلكتروني مؤكد بالفعل'], 400);
        }

        try {
            $target->sendEmailVerificationNotification();
            return response()->json(['message' => 'تم إرسال رابط تأكيد البريد الإلكتروني بنجاح']);
        } catch (\Throwable $e) {
            Log::error("UserManagementController: Failed to resend verification email to {$target->email}: " . $e->getMessage());
            return response()->json(['message' => 'فشل إرسال رابط التأكيد: ' . $e->getMessage()], 500);
        }
    }
}
