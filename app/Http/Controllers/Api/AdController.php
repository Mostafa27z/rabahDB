<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    /**
     * Admin and Team Lead authorization check
     */
    private function authorizeAdminOrTeamLead()
    {
        if (!Auth::check() || (!Auth::user()->isAdmin() && !Auth::user()->isTeamLead())) {
            abort(Response::HTTP_FORBIDDEN, 'المشرفون وقادة الفرق فقط هم المخولون بإدارة الإعلانات.');
        }
    }

    /**
     * List all ads with statistics and search (ADMIN ONLY)
     */
    public function index(Request $request)
    {
        $this->authorizeAdminOrTeamLead();

        $query = Ad::query();

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('distinction', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $ads = $query->orderByDesc('priority')->latest()->paginate($perPage);

        // Calculate statistics
        $stats = [
            'total' => Ad::count(),
            'active' => Ad::where('status', 'active')->count(),
            'paused' => Ad::where('status', 'paused')->count(),
            'distinction' => Ad::whereNotNull('distinction')->where('distinction', '!=', '')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'ads' => $ads,
        ]);
    }

    /**
     * Store a new ad (ADMIN ONLY)
     */
    public function store(Request $request)
    {
        $this->authorizeAdminOrTeamLead();

        $data = $request->validate([
            'property_id'  => 'nullable|integer|exists:properties,id',
            'title'        => 'required|string|max:255',
            'distinction'  => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'button_title' => 'nullable|string|max:100',
            'button_link'  => 'nullable|string',
            'status'       => ['nullable', Rule::in(['active', 'paused', 'draft'])],
            'priority'     => 'nullable|integer',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'image_url'    => 'nullable|string',
        ]);

        $data['status'] = $data['status'] ?? 'active';

        if (!empty($data['property_id']) && empty($data['button_link'])) {
            $data['button_link'] = "/property/{$data['property_id']}";
        }

        // Handle image upload or image_url
        if ($request->hasFile('image')) {
            $data['image_url'] = $request->file('image')->store('ads', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image_url'] = $request->input('image_url');
        }

        // Keep content & cta aligned with description & button for legacy support
        $data['content'] = $data['description'] ?? $data['title'];
        $data['cta_text'] = $data['button_title'] ?? null;
        $data['cta_link'] = $data['button_link'] ?? null;

        // If an ad already exists for this property, update it instead of creating duplicates
        if (!empty($data['property_id'])) {
            $existing = Ad::where('property_id', $data['property_id'])->first();
            if ($existing) {
                $existing->update($data);
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث الإعلان ونشره بنجاح',
                    'ad' => $existing,
                ], 200);
            }
        }

        $ad = Ad::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الإعلان بنجاح',
            'ad' => $ad,
        ], 201);
    }

    /**
     * Show a single ad
     */
    public function show($id)
    {
        $ad = Ad::findOrFail($id);
        return response()->json([
            'success' => true,
            'ad' => $ad,
        ]);
    }

    /**
     * Update an existing ad (ADMIN ONLY)
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAdminOrTeamLead();

        $ad = Ad::findOrFail($id);

        $data = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'distinction'  => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'button_title' => 'nullable|string|max:100',
            'button_link'  => 'nullable|string',
            'status'       => ['sometimes', Rule::in(['active', 'paused', 'draft'])],
            'priority'     => 'nullable|integer',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'image_url'    => 'nullable|string',
        ]);

        // Handle image update
        if ($request->hasFile('image')) {
            if ($ad->image_url && Storage::disk('public')->exists($ad->image_url)) {
                Storage::disk('public')->delete($ad->image_url);
            }
            $data['image_url'] = $request->file('image')->store('ads', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image_url'] = $request->input('image_url');
        }

        if (array_key_exists('description', $data)) {
            $data['content'] = $data['description'] ?? $ad->title;
        }
        if (array_key_exists('button_title', $data)) {
            $data['cta_text'] = $data['button_title'];
        }
        if (array_key_exists('button_link', $data)) {
            $data['cta_link'] = $data['button_link'];
        }

        $ad->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الإعلان بنجاح',
            'ad' => $ad,
        ]);
    }

    /**
     * Toggle ad status between active and paused (ADMIN ONLY)
     */
    public function toggleStatus($id)
    {
        $this->authorizeAdminOrTeamLead();

        $ad = Ad::findOrFail($id);
        $ad->status = ($ad->status === 'active') ? 'paused' : 'active';
        $ad->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الإعلان بنجاح',
            'ad' => $ad,
        ]);
    }

    /**
     * Delete an ad (ADMIN ONLY)
     */
    public function destroy($id)
    {
        $this->authorizeAdminOrTeamLead();

        $ad = Ad::findOrFail($id);

        if ($ad->image_url && Storage::disk('public')->exists($ad->image_url)) {
            Storage::disk('public')->delete($ad->image_url);
        }

        $ad->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإعلان بنجاح',
        ]);
    }

    /**
     * Public endpoint for active ads (Used by AdsSlider and public visitors)
     */
    public function active()
    {
        $ads = Ad::with('property:id,reference,title,price,area_name,city,property_type,area_sqft,purpose')
            ->where('status', 'active')
            ->orderByDesc('priority')
            ->latest()
            ->get();

        $formatted = $ads->map(function ($ad) {
            return [
                'id' => $ad->id,
                'property_id' => $ad->property_id,
                'title' => $ad->title,
                'distinction' => $ad->distinction,
                'description' => $ad->description,
                'button_title' => $ad->button_title,
                'button_link' => $ad->button_link,
                'image_url' => $ad->image_full_url ?? $ad->image_url,
                'status' => $ad->status,
                'property' => $ad->property ? [
                    'id' => $ad->property->id,
                    'reference' => $ad->property->reference,
                    'price' => $ad->property->price,
                    'area_name' => $ad->property->area_name,
                    'city' => $ad->property->city,
                    'property_type' => $ad->property->property_type,
                    'area_sqft' => $ad->property->area_sqft,
                ] : null,
                'created_at' => $ad->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'total' => $formatted->count(),
            'ads' => $formatted,
        ]);
    }
}
