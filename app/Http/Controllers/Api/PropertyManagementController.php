<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyActivity;
use App\Models\PropertyBookmark;
use App\Models\PropertySubmission;
use App\Models\PropertyView;
use App\Models\User;
use App\Services\PropertyNotificationService;
use App\Services\PropertySearchEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PropertyManagementController extends Controller
{
    /**
     * List properties with search, multi-filters, sorting and pagination
     */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        $relations = [
            'owner:id,name,email,role,image,phone,whatsapp_link',
            'creator:id,name,email,role,image',
            'activeAd',
        ];
        if ($user) {
            $relations['bookmarks'] = fn($q) => $q->where('user_id', $user->id);
        }
        $query = Property::with($relations);

        // Role-based visibility & Public storefront vs Dashboard
        $isPublicStorefront = $request->boolean('is_public') || $request->input('is_public') == 1 || $request->is('api/properties/public');

        if ($isPublicStorefront || !$user) {
            // Public visitors and storefront visitors only see active, published, and available properties
            $query->whereIn('status', ['new', 'available']);
        } elseif ($user->isDesigner()) {
            // Designer only sees properties that have been requested/approved for promotion
            $query->where('is_promoted', true);
        } else {
            // Dashboard Explorer: Admin, Team Lead, and Agent can view all properties across all stages
        }

        // Intelligent Multi-token Search Engine
        if ($search = $request->input('search')) {
            $query = PropertySearchEngineService::apply($query, $search);
        }

        // Quick status chips
        if ($quick = $request->input('quick_filter')) {
            if ($quick === 'new') {
                $query->where(fn($q) => $q->where('status', 'new')->orWhere('is_new', true));
            } elseif ($quick === 'updated' || $quick === 'recently_updated') {
                $query->orderByDesc('updated_at');
            } elseif ($quick === 'price_drop') {
                $query->whereNotNull('previous_price')->whereColumn('price', '<', 'previous_price');
            } elseif ($quick === 'back_to_market') {
                $query->where('is_back_to_market', true);
            } elseif ($quick === 'available') {
                $query->whereIn('status', ['available', 'new']);
            } elseif ($quick === 'unavailable') {
                $query->whereIn('status', ['unavailable', 'off_market', 'draft', 'under_payment', 'under_procedure']);
            } elseif ($quick === 'under_negotiation') {
                $query->whereIn('status', ['under_negotiation', 'requested', 'reserved']);
            } elseif ($quick === 'reserved') {
                $query->whereIn('status', ['reserved', 'deposit', 'under_procedure', 'under_payment']);
            } elseif ($quick === 'sold' || $quick === 'closed') {
                $query->whereIn('status', ['sold', 'closed', 'rented']);
            } elseif ($quick === 'ads' || $quick === 'advertised' || $quick === 'has_ad') {
                $query->whereHas('activeAd');
            } elseif ($quick === 'favorites' || $quick === 'bookmarks' || $quick === 'marked') {
                if ($user) {
                    $query->whereHas('bookmarks', fn($q) => $q->where('user_id', $user->id));
                }
            } elseif ($quick === 'needs_update' || $quick === 'stale') {
                $query->whereIn('status', ['new', 'available', 'under_negotiation'])
                      ->where('updated_at', '<=', now()->subDays(60));
            } elseif ($quick === 'promoted' || $quick === 'promotion') {
                $query->where('is_promoted', true);
            } elseif ($quick === 'promotion_pending' || $quick === 'قيد النشر') {
                $query->where('is_promoted', true)->where(function ($q) {
                    $q->whereNull('promotion_completed_at')
                      ->orWhereNull('campaign_links')
                      ->orWhere('campaign_links', '[]')
                      ->orWhereRaw('COALESCE(JSON_LENGTH(campaign_links), 0) = 0');
                });
            } elseif ($quick === 'promotion_completed' || $quick === 'published' || $quick === 'منشور') {
                $query->where('is_promoted', true)->where(function ($q) {
                    $q->whereNotNull('promotion_completed_at')
                      ->whereNotNull('campaign_links')
                      ->where('campaign_links', '!=', '[]')
                      ->whereRaw('COALESCE(JSON_LENGTH(campaign_links), 0) > 0');
                });
            }
        }

        // Direct filters
        if ($purpose = $request->input('purpose')) {
            if ($purpose !== 'all' && $purpose !== 'الكل') {
                if ($purpose === 'بيع' || $purpose === 'للبيع') {
                    $query->where(function($q) {
                        $q->where('purpose', 'sale')->orWhere('search_title', 'like', '%بيع%');
                    });
                } elseif ($purpose === 'إيجار' || $purpose === 'ايجار' || $purpose === 'للإيجار' || $purpose === 'للايجار') {
                    $query->where(function($q) {
                        $q->where('purpose', 'rent')->orWhere('search_title', 'like', '%ايجار%');
                    });
                } elseif ($purpose === 'استثمار' || $purpose === 'للاستثمار') {
                    $query->where(function($q) {
                        $q->where('purpose', 'investment')->orWhere('purpose', 'invest');
                    });
                } else {
                    $query->where('purpose', $purpose);
                }
            }
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all' && $status !== 'الكل') {
                if ($status === 'ads' || $status === 'advertised' || $status === 'has_ad') {
                    $query->whereHas('activeAd');
                } elseif ($status === 'favorites' || $status === 'bookmarks' || $status === 'marked') {
                    if ($user) {
                        $query->whereHas('bookmarks', fn($q) => $q->where('user_id', $user->id));
                    }
                } elseif ($status === 'needs_update' || $status === 'stale') {
                    $query->whereIn('status', ['new', 'available', 'under_negotiation'])
                          ->where('updated_at', '<=', now()->subDays(60));
                } elseif ($status === 'updated' || $status === 'recently_updated') {
                    $query->orderByDesc('updated_at');
                } elseif ($status === 'available') {
                    $query->whereIn('status', ['available', 'new']);
                } elseif ($status === 'unavailable' || $status === 'off_market' || $status === 'draft') {
                    $query->whereIn('status', ['unavailable', 'off_market', 'draft', 'under_payment', 'under_procedure']);
                } elseif ($status === 'under_payment' || $status === 'under_procedure') {
                    $query->whereIn('status', ['under_payment', 'under_procedure']);
                } elseif ($status === 'reserved') {
                    $query->whereIn('status', ['reserved', 'deposit', 'under_procedure', 'under_payment']);
                } elseif ($status === 'under_negotiation' || $status === 'requested') {
                    $query->whereIn('status', ['under_negotiation', 'requested', 'reserved']);
                } elseif ($status === 'sold' || $status === 'closed') {
                    $query->whereIn('status', ['sold', 'closed', 'rented']);
                } elseif ($status === 'promoted' || $status === 'promotion') {
                    $query->where('is_promoted', true);
                } elseif ($status === 'promotion_pending' || $status === 'قيد النشر') {
                    $query->where('is_promoted', true)->where(function ($q) {
                        $q->whereNull('promotion_completed_at')
                          ->orWhereNull('campaign_links')
                          ->orWhere('campaign_links', '[]')
                          ->orWhereRaw('COALESCE(JSON_LENGTH(campaign_links), 0) = 0');
                    });
                } elseif ($status === 'promotion_completed' || $status === 'published' || $status === 'منشور') {
                    $query->where('is_promoted', true)->where(function ($q) {
                        $q->whereNotNull('promotion_completed_at')
                          ->whereNotNull('campaign_links')
                          ->where('campaign_links', '!=', '[]')
                          ->whereRaw('COALESCE(JSON_LENGTH(campaign_links), 0) > 0');
                    });
                } else {
                    $query->where('status', $status);
                }
            }
        }

        if ($request->boolean('favorites') || $request->boolean('is_favorite') || $request->boolean('bookmarked')) {
            if ($user) {
                $query->whereHas('bookmarks', fn($q) => $q->where('user_id', $user->id));
            }
        }

        if ($type = $request->input('property_type')) {
            if ($type !== 'all' && $type !== 'الكل' && $type !== 'جميع أنواع العقارات' && $type !== 'جميع التصنيفات') {
                $query->where('property_type', $type);
            }
        }

        // Category filter (أراضي / سكني / تجاري أو تصنيف مخصص)
        if ($category = $request->input('category')) {
            if ($category !== 'all' && $category !== 'الكل' && $category !== 'جميع التصنيفات' && $category !== 'جميع أنواع العقارات') {
                if ($category === 'lands' || $category === 'أراضي' || $category === 'land') {
                    $query->where(function($q) {
                        $q->where('property_type', 'like', '%أرض%')
                          ->orWhere('property_type', 'like', '%مزرعه%')
                          ->orWhere('property_type', 'like', '%مزرعة%')
                          ->orWhere('title', 'like', '%أرض%');
                    });
                } elseif ($category === 'commercial' || $category === 'تجاري') {
                    $query->where(function($q) {
                        $q->where('property_type', 'like', '%تجاري%')
                          ->orWhere('property_type', 'like', '%محل%')
                          ->orWhere('property_type', 'like', '%مستودع%')
                          ->orWhere('property_type', 'like', '%مكتب%')
                          ->orWhere('property_type', 'like', '%معارض%')
                          ->orWhere('property_type', 'like', '%بناية%')
                          ->orWhere('property_type', 'like', '%مصنع%')
                          ->orWhere('property_type', 'like', '%شبرات%');
                    });
                } elseif ($category === 'residential' || $category === 'سكني') {
                    $query->where(function($q) {
                        $q->where('property_type', 'like', '%شقة%')
                          ->orWhere('property_type', 'like', '%فيلا%')
                          ->orWhere('property_type', 'like', '%بيت عربي%')
                          ->orWhere('property_type', 'like', '%تاون هاوس%');
                    });
                } else {
                    if (!$request->input('property_type')) {
                        $query->where('property_type', $category);
                    }
                }
            }
        }

        if ($area = $request->input('area_name')) {
            $query->where('area_name', $area);
        }

        if ($city = $request->input('city')) {
            $query->where(function($q) use ($city) {
                $q->where('city', 'like', "%{$city}%")
                  ->orWhere('area_name', 'like', "%{$city}%");
            });
        }

        if ($beds = $request->input('bedrooms') ?? $request->input('beds')) {
            if ($beds !== 'all' && $beds !== 'الكل') {
                $query->where(function($q) use ($beds) {
                    if ($beds === 'studio' || $beds === 'استوديو' || $beds === '0') {
                        $q->where('title', 'like', '%استوديو%')
                          ->orWhere('title', 'like', '%studio%')
                          ->orWhere('property_details', 'like', '%استوديو%');
                    } else {
                        $num = (int)$beds;
                        $q->where('property_details', 'like', "%{$num}%غرفة%")
                          ->orWhere('property_details', 'like', "%{$num}%غرف%")
                          ->orWhere('property_details', 'like', "%{$num}%bed%")
                          ->orWhere('description', 'like', "%{$num}%غرفة%")
                          ->orWhere('title', 'like', "%{$num}%غرفة%");
                    }
                });
            }
        }

        if ($baths = $request->input('bathrooms') ?? $request->input('baths')) {
            if ($baths !== 'all' && $baths !== 'الكل') {
                $num = (int)$baths;
                $query->where(function($q) use ($num) {
                    $q->where('property_details', 'like', "%{$num}%حمام%")
                      ->orWhere('property_details', 'like', "%{$num}%bath%")
                      ->orWhere('description', 'like', "%{$num}%حمام%");
                });
            }
        }

        if ($agentId = $request->input('user_id') ?? $request->input('agent_id')) {
            if ($agentId !== 'all') {
                $query->where(function($q) use ($agentId) {
                    $q->where('user_id', $agentId)
                      ->orWhere('created_by_id', $agentId);
                });
            }
        }

        if ($minPrice = $request->input('min_price')) {
            $query->where('price', '>=', (float)$minPrice);
        }

        if ($maxPrice = $request->input('max_price')) {
            $query->where('price', '<=', (float)$maxPrice);
        }

        if ($minRoi = $request->input('min_roi')) {
            $query->where('roi_percentage', '>=', (float)$minRoi);
        }

        if ($request->boolean('has_price_drop')) {
            $query->whereNotNull('previous_price')->whereColumn('price', '<', 'previous_price');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'newest');
        if ($quick === 'updated' || $quick === 'recently_updated' || $status === 'updated' || $status === 'recently_updated') {
            $sortBy = 'recently_updated';
        }
        match ($sortBy) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'roi_desc' => $query->orderByDesc('roi_percentage'),
            'recently_updated' => $query->orderByDesc('updated_at'),
            default => $query->latest(),
        };

        $rawPerPage = (int)($request->input('per_page') ?? $request->input('limit') ?? 15);
        $perPage = $rawPerPage > 0 ? min($rawPerPage, 1000) : 15;
        $paginated = $query->paginate($perPage);

        // Telemetry: Log zero search results for expansion of real-world synonyms
        if ($search && $paginated->total() === 0) {
            \Illuminate\Support\Facades\Log::info("Property search returned 0 results: [{$search}]");
        }

        // Privacy: Source fields (source_name, source_files, source_notes) are strictly visible to Admin or Property Creator/Owner
        $paginated->getCollection()->transform(function ($prop) use ($user) {
            $canViewSource = $user && (
                $user->isAdmin() ||
                $user->id === $prop->user_id ||
                $user->id === $prop->created_by_id
            );
            if (!$canViewSource) {
                $prop->makeHidden(['source_name', 'source_files', 'source_notes', 'source_file_urls']);
            }
            return $prop;
        });

        return response()->json($paginated);
    }

    /**
     * Show single property
     */
    public function show($id)
    {
        $query = Property::with([
            'owner:id,name,email,role,image,phone,whatsapp_link,bio,instagram_link,tiktok_link,facebook_link,twitter_link,linkedin_link,snapchat_link,telegram_link',
            'creator:id,name',
            'activities.user:id,name,role'
        ]);

        if (is_numeric($id)) {
            $property = (clone $query)->where('id', (int)$id)->first();
            if (!$property) {
                $property = (clone $query)->where('reference', $id)->first();
            }
        } else {
            $property = (clone $query)->where('reference', $id)->first();
        }

        if (!$property) {
            abort(404, 'Property not found');
        }

        // Privacy: source fields are strictly private to Admin or the Assigned/Creator Agent
        $user = auth('sanctum')->user() ?? auth()->user();
        $canViewSource = $user && (
            $user->isAdmin() ||
            $user->id === $property->user_id ||
            $user->id === $property->created_by_id
        );
        if (!$canViewSource) {
            $property->makeHidden(['source_name', 'source_files', 'source_notes', 'source_file_urls']);
        }

        return response()->json($property);
    }

    /**
     * Store new property
     */
    public function store(Request $request)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user || (!$user->isAdmin() && !$user->isTeamLead() && !$user->isAgent())) {
            return response()->json(['message' => 'غير مصرح لك بإضافة عقارات في لوحة التحكم.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'property_type' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|in:sale,rent,investment,invest',
            'price' => 'nullable|numeric|min:0',
            'area_sqft' => 'nullable|numeric|min:0',
            'price_per_foot' => 'nullable|numeric|min:0',
            'area_name' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'google_map_url' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'description' => 'nullable|string',
            'property_details' => 'nullable|string',
            'status' => 'nullable|string|in:new,available,unavailable,under_negotiation,reserved,closed,sold,rented,under_procedure,under_payment,paused,off_market,draft',
            'priority' => 'nullable|string',
            'company_details' => 'nullable|string',
            'company_images.*' => 'nullable',
            'source_name' => 'nullable|string|max:255',
            'source_files.*' => 'nullable',
            'source_notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'assets_notes' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'cover_image' => 'nullable',
            'images.*' => 'nullable',
            'brochure_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'seo_keywords' => 'nullable|string',
        ]);




        // Auto-calculate price per foot if area exists
        if (!empty($validated['price']) && !empty($validated['area_sqft']) && $validated['area_sqft'] > 0) {
            $validated['price_per_foot'] = round($validated['price'] / $validated['area_sqft'], 2);
        }

        // Generate clean reference matching Shj{ym}-{id}
        $nextId = (Property::max('id') ?? 0) + 1;
        $validated['reference'] = 'Shj' . now()->format('ym') . '-' . $nextId;
        $validated['created_by_id'] = $user?->id ?? 1;

        // Assigned Agent
        if (($user && $user->isAgent()) || empty($validated['user_id'])) {
            $validated['user_id'] = $user?->id ?? 1;
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('properties/covers', 'public');
            $validated['cover_image'] = $coverPath;
            $validated['cover_image_watermark'] = $coverPath;
        }

        // Handle gallery images
        if ($request->hasFile('images')) {
            $galleryPaths = [];
            foreach ($request->file('images') as $img) {
                $path = $img->store('properties/gallery', 'public');
                $galleryPaths[] = $path;
            }
            $validated['images'] = $galleryPaths;
        }

        // Handle Company Database Images
        if ($request->hasFile('company_images')) {
            $companyPaths = [];
            foreach ($request->file('company_images') as $img) {
                $path = $img->store('properties/company_database', 'public');
                $companyPaths[] = $path;
            }
            $validated['company_images'] = $companyPaths;
        }

        // Handle Source Files (Confidential)
        if ($request->hasFile('source_files')) {
            $sourceFiles = [];
            foreach ($request->file('source_files') as $file) {
                $path = $file->store('properties/sources', 'public');
                $sourceFiles[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                ];
            }
            $validated['source_files'] = $sourceFiles;
        }

        // Handle Brochure PDF
        if ($request->hasFile('brochure_pdf')) {
            $pdfPath = $request->file('brochure_pdf')->store('properties/brochures', 'public');
            $validated['brochure_pdf'] = $pdfPath;
        }

        $validated['status'] = $validated['status'] ?? 'new';
        $validated['status_changed_at'] = now();
        $validated['is_new'] = true;

        // Parse designer IDs selected in the wizard
        $designerIds = [];
        if ($request->has('designer_ids')) {
            $rawDesignerIds = $request->input('designer_ids');
            if (is_string($rawDesignerIds)) {
                $decoded = json_decode($rawDesignerIds, true);
                $rawDesignerIds = is_array($decoded) ? $decoded : explode(',', $rawDesignerIds);
            }
            if (is_array($rawDesignerIds)) {
                $designerIds = array_values(array_filter(array_map('intval', $rawDesignerIds)));
            }
        }

        $validated['designer_ids'] = !empty($designerIds) ? $designerIds : null;

        // Auto-promote for designers if selected
        if (!empty($designerIds)) {
            $validated['is_promoted'] = true;
            $validated['promoted_at'] = now();
            $validated['promoted_by'] = $user?->id;
            $validated['promoted_designer_id'] = $designerIds[0];
        } else {
            $validated['is_promoted'] = false;
        }

        $property = Property::create($validated);

        // Record activity
        $userName = $user ? $user->name : 'المسؤول';
        PropertyActivity::create([
            'property_id' => $property->id,
            'user_id' => $user?->id ?? 1,
            'activity_type' => 'created',
            'title' => 'تمت إضافة عقار جديد',
            'description' => "قام {$userName} بإضافة العقار: {$property->title}",
            'new_data' => $property->toArray(),
        ]);

        // Instant notification to Team (strictly sending to selected designers only)
        PropertyNotificationService::notifyTeam(
            $property,
            'created',
            'إضافة عقار جديد',
            null,
            $property->price ? (float)$property->price : null,
            $userName,
            $user,
            $designerIds
        );

        return response()->json([
            'success' => true,
            'message' => 'Property created successfully',
            'property' => $property->load('owner'),
        ], 201);
    }

    /**
     * Update property with price tracking & status history
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        $property = Property::findOrFail($id);

        // Authorization check for editing (Deny by default)
        if ($user->isAdmin()) {
            // Admin can update any property
        } elseif ($user->isTeamLead()) {
            $teamAgentIds = $user->agents()->pluck('id')->toArray();
            if ($property->user_id !== $user->id && !in_array($property->user_id, $teamAgentIds)) {
                abort(403, 'غير مصرح لك بتعديل هذا العقار. يحق لقائد الفريق تعديل عقاراته وعقارات فريقه فقط.');
            }
        } elseif ($user->isAgent()) {
            if ($property->user_id !== $user->id) {
                abort(403, 'غير مصرح لك بتعديل هذا العقار. يحق للوكيل تعديل عقاراته الخاصة فقط.');
            }
        } else {
            abort(403, 'غير مصرح لك بتعديل هذا العقار.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'property_type' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|in:sale,rent,investment,invest',
            'price' => 'nullable|numeric|min:0',
            'area_sqft' => 'nullable|numeric|min:0',
            'price_per_foot' => 'nullable|numeric|min:0',
            'area_name' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'google_map_url' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'description' => 'nullable|string',
            'property_details' => 'nullable|string',
            'status' => 'nullable|string|in:new,available,unavailable,under_negotiation,reserved,closed,sold,rented,under_procedure,under_payment,paused,off_market,draft',
            'priority' => 'nullable|string',
            'company_details' => 'nullable|string',
            'company_images.*' => 'nullable',
            'source_name' => 'nullable|string|max:255',
            'source_files.*' => 'nullable',
            'source_notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'assets_notes' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'cover_image' => 'nullable',
            'images.*' => 'nullable',
            'brochure_pdf' => 'nullable',
            'seo_keywords' => 'nullable|string',
        ]);


        // Price change & tracking
        if (isset($validated['price']) && (float)$validated['price'] !== (float)$property->price) {
            $oldPrice = (float)$property->price;
            $newPrice = (float)$validated['price'];

            $validated['previous_price'] = $oldPrice;
            $validated['price_changed_at'] = now();

            $activityType = $newPrice < $oldPrice ? 'price_drop' : 'price_changed';
            $dropPercent = $oldPrice > 0 ? round((($oldPrice - $newPrice) / $oldPrice) * 100, 1) : 0;

            PropertyActivity::create([
                'property_id' => $property->id,
                'user_id' => $user?->id ?? $property->user_id,
                'activity_type' => $activityType,
                'title' => $activityType === 'price_drop' ? "انخفاض في سعر العقار ({$dropPercent}%)" : "تعديل سعر العقار",
                'description' => "تغير السعر من {$oldPrice} إلى {$newPrice} {$property->currency}",
                'old_data' => ['price' => $oldPrice],
                'new_data' => ['price' => $newPrice],
            ]);
        }



        // Status change & tracking
        if (isset($validated['status']) && $validated['status'] !== $property->status) {
            $oldStatus = $property->status;
            $newStatus = $validated['status'];

            $validated['status_changed_at'] = now();
            if (in_array($oldStatus, ['sold', 'rented', 'under_procedure']) && in_array($newStatus, ['available', 'new'])) {
                $validated['is_back_to_market'] = true;
            }

            $userName = $user ? $user->name : 'المسؤول';
            PropertyActivity::create([
                'property_id' => $property->id,
                'user_id' => $user?->id ?? $property->user_id,
                'activity_type' => 'status_changed',
                'title' => 'تغيير حالة العقار',
                'description' => "تغيرت حالة العقار بواسطة {$userName} من {$oldStatus} إلى {$newStatus}",
                'old_data' => ['status' => $oldStatus],
                'new_data' => ['status' => $newStatus],
            ]);
        }

        // Handle cover image
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('properties/covers', 'public');
            $validated['cover_image'] = $coverPath;
            $validated['cover_image_watermark'] = $coverPath;
        }

        // Handle gallery images
        if ($request->hasFile('images')) {
            $galleryPaths = is_array($property->images) ? $property->images : [];
            foreach ($request->file('images') as $img) {
                $path = $img->store('properties/gallery', 'public');
                $galleryPaths[] = $path;
            }
            $validated['images'] = $galleryPaths;
        }

        // Handle Company Database Images
        if ($request->hasFile('company_images')) {
            $companyPaths = is_array($property->company_images) ? $property->company_images : [];
            foreach ($request->file('company_images') as $img) {
                $path = $img->store('properties/company_database', 'public');
                $companyPaths[] = $path;
            }
            $validated['company_images'] = $companyPaths;
        }

        // Handle Source Files (Confidential)
        if ($request->hasFile('source_files')) {
            $sourceFiles = is_array($property->source_files) ? $property->source_files : [];
            foreach ($request->file('source_files') as $file) {
                $path = $file->store('properties/sources', 'public');
                $sourceFiles[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                ];
            }
            $validated['source_files'] = $sourceFiles;
        }

        // Handle Brochure PDF
        if ($request->hasFile('brochure_pdf')) {
            $pdfPath = $request->file('brochure_pdf')->store('properties/brochures', 'public');
            $validated['brochure_pdf'] = $pdfPath;
        }

        // Handle designer assignment updates
        if ($request->has('designer_ids')) {
            $rawDesignerIds = $request->input('designer_ids');
            if (is_string($rawDesignerIds)) {
                $decoded = json_decode($rawDesignerIds, true);
                $rawDesignerIds = is_array($decoded) ? $decoded : explode(',', $rawDesignerIds);
            }
            if (is_array($rawDesignerIds)) {
                $designerIds = array_values(array_filter(array_map('intval', $rawDesignerIds)));
            } else {
                $designerIds = [];
            }
            $validated['designer_ids'] = !empty($designerIds) ? $designerIds : null;
            if (!empty($designerIds)) {
                $validated['promoted_designer_id'] = $designerIds[0];
                $validated['is_promoted'] = true;
                if (!$property->promoted_at) {
                    $validated['promoted_at'] = now();
                    $validated['promoted_by'] = $user?->id;
                }
            } else {
                $validated['promoted_designer_id'] = null;
                $validated['is_promoted'] = false;
            }
        }

        $validated['updated_by_id'] = $user?->id ?? $property->user_id;
        $property->update($validated);

        // Instant notification to Team on price & status changes
        $userName = $user ? $user->name : 'المسؤول';
        if (isset($oldPrice) && isset($newPrice) && $oldPrice !== $newPrice) {
            PropertyNotificationService::notifyTeam(
                $property,
                'price_changed',
                'تعديل سعر العقار',
                $oldPrice,
                $newPrice,
                $userName,
                $user
            );
        }

        if (isset($oldStatus) && isset($newStatus) && $oldStatus !== $newStatus) {
            $statusLabels = [
                'new' => 'جديد',
                'available' => 'متاح',
                'under_negotiation' => 'قيد التفاوض',
                'reserved' => 'استلام العربون والتعاقد',
                'sold' => 'تمت العملية (مباع)',
                'closed' => 'مغلق',
                'rented' => 'مؤجر',
                'under_procedure' => 'تحت الإجراء',
                'under_payment' => 'قيد الدفع',
                'unavailable' => 'غير متاح',
                'paused' => 'موقوف مؤقتاً',
                'off_market' => 'خارج السوق',
                'draft' => 'مسودة',
            ];
            $newStatusLabel = $statusLabels[$newStatus] ?? $newStatus;

            PropertyNotificationService::notifyTeam(
                $property,
                'status_changed',
                "تغيير حالة العقار إلى ({$newStatusLabel})",
                null,
                (float)$property->price,
                $userName,
                $user
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Property updated successfully',
            'property' => $property->load('owner'),
        ]);
    }

    /**
     * Delete property
     */
    public function destroy($id)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        $property = Property::findOrFail($id);

        if ($user->isAdmin()) {
            // Admin can delete any property
        } elseif ($user->isTeamLead()) {
            $teamAgentIds = $user->agents()->pluck('id')->toArray();
            if ($property->user_id !== $user->id && !in_array($property->user_id, $teamAgentIds)) {
                abort(403, 'غير مصرح لك بحذف هذا العقار.');
            }
        } elseif ($user->isAgent()) {
            if ($property->user_id !== $user->id) {
                abort(403, 'غير مصرح لك بحذف هذا العقار.');
            }
        } else {
            abort(403, 'غير مصرح لك بحذف هذا العقار.');
        }

        $userName = $user ? $user->name : 'المسؤول';
        PropertyNotificationService::notifyTeam(
            $property,
            'deleted',
            'حذف عقار من المنظومة',
            null,
            $property->price ? (float)$property->price : null,
            $userName,
            $user
        );

        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Property deleted successfully',
        ]);
    }

    /**
     * Saved Views: List for current user
     */
    public function getViews()
    {
        $user = auth()->user();
        $views = PropertyView::where('user_id', $user->id)->get();
        return response()->json($views);
    }

    /**
     * Saved Views: Save new view
     */
    public function storeView(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'view_mode' => 'required|string|in:table,card,kanban,map',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);

        $view = PropertyView::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'view_mode' => $validated['view_mode'],
            'filters' => $validated['filters'] ?? [],
            'columns' => $validated['columns'] ?? [],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return response()->json($view, 201);
    }

    /**
     * Saved Views: Delete view
     */
    public function destroyView($id)
    {
        $user = auth()->user();
        PropertyView::where('user_id', $user->id)->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Return list of available property types for filtering
     */
    public function getTypes()
    {
        $dbTypes = Property::whereNotNull('property_type')
            ->where('property_type', '!=', '')
            ->distinct()
            ->pluck('property_type')
            ->filter()
            ->values();

        $standardTypes = collect([
            'فيلا مستقلة',
            'فيلا دوبلكس',
            'شقة',
            'بيت عربي',
            'أرض سكنية',
            'أرض تجارية',
            'أرض صناعية',
            'أرض زراعية',
            'بناية',
            'مكتب',
            'محل تجاري',
            'معارض',
            'مستودعات',
            'شبرات',
            'فندق',
            'مزرعة',
            'مصنع',
            'أرض سكنية استثماري',
            'أرض صناعية مسورة',
        ]);

        $merged = $dbTypes->merge($standardTypes)->unique()->values();

        return response()->json([
            'data' => $merged->map(fn($t) => [
                'value' => $t,
                'label' => $t,
            ])
        ]);
    }

    /**
     * Portfolio for a specific agent/user
     */
    public function portfolio(Request $request, $userId)
    {
        // 1. Find target user by ID or username
        $targetUser = User::with(['agents'])
            ->where('id', $userId)
            ->orWhere('username', $userId)
            ->first();

        if (!$targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 2. Increment portfolio views counter
        $targetUser->increment('portfolio_views');

        // 3. Determine properties visible in this user's portfolio
        $allowedUserIds = [$targetUser->id];

        if ($targetUser->isAdmin()) {
            $allowedUserIds = User::pluck('id')->toArray();
        } elseif ($targetUser->isTeamLead()) {
            $agentIds = $targetUser->agents()->pluck('id')->toArray();
            $adminIds = User::where('role', 'admin')->pluck('id')->toArray();
            $allowedUserIds = array_unique(array_merge([$targetUser->id], $agentIds, $adminIds));
        } elseif ($targetUser->isAgent()) {
            if ($targetUser->team_lead_id) {
                $allowedUserIds[] = $targetUser->team_lead_id;
            }
            $adminIds = User::where('role', 'admin')->pluck('id')->toArray();
            $allowedUserIds = array_unique(array_merge($allowedUserIds, $adminIds));
        }

        // 4. Query properties
        $query = Property::with([
            'owner:id,name,email,role,image,phone,whatsapp_link',
            'creator:id,name,email,role,image'
        ])
        ->whereIn('user_id', $allowedUserIds)
        ->whereIn('status', ['new', 'available']);

        // Intelligent Multi-token Search Engine
        if ($search = $request->input('search')) {
            $query = PropertySearchEngineService::apply($query, $search);
        }

        // Filters
        if ($type = $request->input('property_type')) {
            if ($type !== 'all' && $type !== 'الكل' && $type !== 'جميع أنواع العقارات') {
                $query->where('property_type', $type);
            }
        }

        if ($beds = $request->input('bedrooms') ?? $request->input('beds')) {
            if ($beds !== 'all' && $beds !== 'الكل') {
                $query->where(function($q) use ($beds) {
                    if ($beds === 'studio' || $beds === 'استوديو' || $beds === '0') {
                        $q->where('title', 'like', '%استوديو%')
                          ->orWhere('title', 'like', '%studio%')
                          ->orWhere('property_details', 'like', '%استوديو%');
                    } else {
                        $num = (int)$beds;
                        $q->where('property_details', 'like', "%{$num}%غرفة%")
                          ->orWhere('property_details', 'like', "%{$num}%غرف%")
                          ->orWhere('property_details', 'like', "%{$num}%bed%")
                          ->orWhere('description', 'like', "%{$num}%غرفة%")
                          ->orWhere('title', 'like', "%{$num}%غرفة%");
                    }
                });
            }
        }

        if ($priceRange = $request->input('price_range')) {
            if ($priceRange === '<1M' || $priceRange === 'أقل من 1 مليون درهم') {
                $query->where('price', '<', 1000000);
            } elseif ($priceRange === '1-2M' || $priceRange === '1 - 2 مليون درهم') {
                $query->whereBetween('price', [1000000, 2000000]);
            } elseif ($priceRange === '2-3M' || $priceRange === '2 - 3 مليون درهم') {
                $query->whereBetween('price', [2000000, 3000000]);
            } elseif ($priceRange === '3-5M' || $priceRange === '3 - 5 مليون درهم') {
                $query->whereBetween('price', [3000000, 5000000]);
            } elseif ($priceRange === '>5M' || $priceRange === 'أكثر من 5 مليون درهم') {
                $query->where('price', '>', 5000000);
            }
        }

        if ($minPrice = $request->input('min_price')) {
            $query->where('price', '>=', (float)$minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('price', '<=', (float)$maxPrice);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'newest');
        match ($sortBy) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'roi_desc' => $query->orderByDesc('roi_percentage'),
            'recently_updated' => $query->orderByDesc('updated_at'),
            default => $query->latest(),
        };

        $rawPerPage = (int)($request->input('per_page') ?? $request->input('limit') ?? 12);
        $perPage = $rawPerPage > 0 ? min($rawPerPage, 1000) : 12;
        $paginated = $query->paginate($perPage);

        if ($search && $paginated->total() === 0) {
            \Illuminate\Support\Facades\Log::info("Portfolio property search returned 0 results: [{$search}]");
        }

        // 5. Build Owner Data
        $ratingsQuery = $targetUser->ratingsReceived()->with('rater:id,name,email,role,image')->where('status', 'public');
        $ratings = $ratingsQuery->get();

        $formattedRatings = $ratings->map(function($rating) {
            return [
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
        });

        $averageRating = $targetUser->ratingsReceived()->where('status', 'public')->avg('rating');

        $ownerData = [
            'id' => $targetUser->id,
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role' => $targetUser->role,
            'team_lead_id' => $targetUser->team_lead_id,
            'username' => $targetUser->username,
            'image' => $targetUser->image ? asset('storage/' . $targetUser->image) : null,
            'phone' => $targetUser->phone,
            'instagram_link' => $targetUser->instagram_link,
            'tiktok_link' => $targetUser->tiktok_link,
            'whatsapp_link' => $targetUser->whatsapp_link,
            'facebook_link' => $targetUser->facebook_link,
            'linkedin_link' => $targetUser->linkedin_link,
            'twitter_link' => $targetUser->twitter_link,
            'snapchat_link' => $targetUser->snapchat_link,
            'telegram_link' => $targetUser->telegram_link,
            'bio' => $targetUser->bio,
            'portfolio_views' => $targetUser->portfolio_views,
            'ratings' => $formattedRatings,
            'average_rating' => $averageRating ? round($averageRating, 2) : null,
            'total_ratings' => $targetUser->ratingsReceived()->where('status', 'public')->count(),
        ];

        return response()->json([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'owner' => $ownerData,
        ]);
    }

    /**
     * Get up to 12 latest properties for the Home Page
     */
    public function homeProperties(Request $request)
    {
        // Fetch latest 12 properties for the Home Page (sorted by newest ID)
        $properties = Property::with(['owner'])
            ->where(function ($query) {
                $query->whereIn('status', ['available', 'new', 'under_procedure'])
                    ->orWhereNull('status');
            })
            ->orderBy('id', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $properties,
            'count' => $properties->count(),
            'max' => 12,
        ]);
    }

    /**
     * Quick toggle for is_featured (Max 12 limit) - Protected (Admin & Team Lead Only)
     */
    public function toggleFeatured($id)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user || (!$user->isAdmin() && !$user->isTeamLead())) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتعديل العقارات المميزة للصفحة الرئيسية.'
            ], 403);
        }

        $property = Property::findOrFail($id);

        if (!$property->is_featured) {
            $count = Property::where('is_featured', true)->count();
            if ($count >= 12) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، تم الوصول للحد الأقصى (12 عقاراً) المعروضة في الصفحة الرئيسية. يرجى إلغاء تفعيل عقار آخر أولاً.',
                ], 422);
            }
            $property->is_featured = true;
        } else {
            $property->is_featured = false;
        }

        $property->save();

        return response()->json([
            'success' => true,
            'is_featured' => $property->is_featured,
            'message' => $property->is_featured ? 'تم تمييز العقار للعرض في الصفحة الرئيسية' : 'تمت إزالة العقار من الصفحة الرئيسية',
            'count' => Property::where('is_featured', true)->count(),
        ]);
    }

    /**
     * Get the count of properties currently marked as featured for the Home Page
     */
    public function featuredCount()
    {
        $count = Property::where('is_featured', true)->count();
        return response()->json([
            'success' => true,
            'count' => $count,
            'max' => 12,
            'is_full' => $count >= 12,
        ]);
    }

    /**
     * Toggle bookmark/mark for authenticated user/agent
     */
    public function toggleBookmark($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى تسجيل الدخول أولاً لإضافة العقار إلى المفضلة'
            ], 401);
        }

        $property = Property::findOrFail($id);
        $bookmark = PropertyBookmark::where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->first();

        if ($bookmark) {
            $bookmark->delete();
            $isBookmarked = false;
            $message = 'تمت إزالة العقار من المفضلة';
        } else {
            PropertyBookmark::create([
                'user_id' => $user->id,
                'property_id' => $property->id,
            ]);
            $isBookmarked = true;
            $message = 'تمت إضافة العقار إلى المفضلة';
        }

        return response()->json([
            'success' => true,
            'is_bookmarked' => $isBookmarked,
            'message' => $message,
        ]);
    }

    /**
     * Admin manually requests promotion/design for a property
     */
    public function requestPromotion(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا الإجراء مخصص للمسؤول (Admin) فقط',
            ], 403);
        }

        $property = Property::findOrFail($id);

        $designerIds = [];
        if ($request->has('designer_ids')) {
            $rawDesignerIds = $request->input('designer_ids');
            if (is_string($rawDesignerIds)) {
                $decoded = json_decode($rawDesignerIds, true);
                $rawDesignerIds = is_array($decoded) ? $decoded : explode(',', $rawDesignerIds);
            }
            if (is_array($rawDesignerIds)) {
                $designerIds = array_values(array_filter(array_map('intval', $rawDesignerIds)));
            }
        }

        $property->is_promoted = true;
        $property->promoted_at = now();
        $property->promoted_by = $user->id;
        if (!empty($designerIds)) {
            $property->designer_ids = $designerIds;
            $property->promoted_designer_id = $designerIds[0];
        }
        $property->save();

        // Record activity
        PropertyActivity::create([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'activity_type' => 'promotion_requested',
            'title' => 'طلب ترويج وتصميم عقار',
            'description' => "قام {$user->name} بطلب ترويج وتصميم للعقار: {$property->title}",
            'new_data' => [
                'is_promoted' => true,
                'promoted_at' => $property->promoted_at,
                'designer_ids' => $property->designer_ids,
            ],
        ]);

        // Send email notification strictly to selected designers
        PropertyNotificationService::notifyDesignerPromotionRequest($property, $user, !empty($designerIds) ? $designerIds : null);

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل طلب الترويج وإرسال إشعار للمصمم بنجاح',
            'property' => $property->fresh(['promoter', 'promotedDesigner']),
        ]);
    }

    /**
     * Designer or Admin submits campaign links and notes for a promoted property
     */
    public function submitPromotion(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user || (!$user->isDesigner() && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإضافة تفاصيل الترويج والتصميم',
            ], 403);
        }

        $validated = $request->validate([
            'campaign_links' => 'nullable|array',
            'campaign_links.*.url' => 'required|url',
            'campaign_links.*.platform' => 'nullable|string',
            'designer_notes' => 'nullable|string',
            'designer_id' => 'nullable|exists:users,id',
        ]);

        $property = Property::findOrFail($id);

        // Determine which designer is submitting these links
        // A designer can ONLY submit for himself! Only an Admin can submit on behalf of other designers.
        if ($user->isDesigner()) {
            $targetDesignerId = $user->id;
            $targetDesigner = $user;
        } else {
            $targetDesignerId = $request->input('designer_id') ?: $user->id;
            $targetDesigner = User::find($targetDesignerId) ?: $user;
        }

        $newLinks = $validated['campaign_links'] ?? [];
        $designerNotes = $validated['designer_notes'] ?? null;

        // Maintain structured per-designer campaigns
        $campaigns = is_array($property->designer_campaigns) ? $property->designer_campaigns : [];
        $campaigns[(string)$targetDesigner->id] = [
            'designer_id' => $targetDesigner->id,
            'designer_name' => $targetDesigner->name,
            'designer_email' => $targetDesigner->email,
            'designer_image' => $targetDesigner->image,
            'links' => $newLinks,
            'designer_notes' => $designerNotes,
            'submitted_at' => now()->toIso8601String(),
        ];
        $property->designer_campaigns = $campaigns;

        // Aggregate all links across all designers so legacy readers continue working
        $allLinks = [];
        foreach ($campaigns as $camp) {
            if (!empty($camp['links']) && is_array($camp['links'])) {
                foreach ($camp['links'] as $link) {
                    $allLinks[] = array_merge($link, [
                        'designer_id' => $camp['designer_id'] ?? null,
                        'designer_name' => $camp['designer_name'] ?? null,
                    ]);
                }
            }
        }

        $property->campaign_links = $allLinks;
        $property->designer_notes = $designerNotes;
        $property->promotion_completed_at = now();
        $property->promoted_designer_id = $targetDesigner->id;
        $property->is_promoted = true;
        $property->save();

        // Record activity
        PropertyActivity::create([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'activity_type' => 'promotion_completed',
            'title' => 'تم إنجاز تفاصيل الترويج والتصميم',
            'description' => "قام {$targetDesigner->name} بتحديث روابط الحملة وتفاصيل الترويج للعقار: {$property->title}",
            'new_data' => [
                'designer_id' => $targetDesigner->id,
                'designer_name' => $targetDesigner->name,
                'campaign_links' => $newLinks,
                'designer_notes' => $designerNotes,
                'promotion_completed_at' => $property->promotion_completed_at,
            ],
        ]);

        // Notify Admin that designer has submitted links and completed design
        PropertyNotificationService::notifyAdminPromotionCompleted($property, $targetDesigner);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ روابط وتفاصيل الترويج وإشعار الإدارة بنجاح',
            'property' => $property->fresh(['promoter', 'promotedDesigner']),
        ]);
    }

    /**
     * Admin cancels promotion for a property
     */
    public function cancelPromotion(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا الإجراء مخصص للمسؤول (Admin) فقط',
            ], 403);
        }

        $property = Property::findOrFail($id);

        $assignedDesignerIds = !empty($property->designer_ids)
            ? (array) $property->designer_ids
            : ($property->promoted_designer_id ? [$property->promoted_designer_id] : []);

        $property->is_promoted = false;
        $property->promoted_at = null;
        $property->promoted_by = null;
        $property->promotion_completed_at = null;
        $property->save();

        // Record activity
        PropertyActivity::create([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'activity_type' => 'promotion_cancelled',
            'title' => 'تم إلغاء الترويج',
            'description' => "قام المسؤول {$user->name} بإلغاء ترويج العقار: {$property->title}",
            'new_data' => [
                'is_promoted' => false,
                'cancelled_at' => now(),
            ],
        ]);

        // Send email notification to assigned designers
        PropertyNotificationService::notifyDesignerPromotionCancelled($property, $user, $assignedDesignerIds);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الترويج وإرسال إشعار للمصمم بنجاح',
            'property' => $property->fresh(['promoter', 'promotedDesigner']),
        ]);
    }

    /**
     * Ensure property_submissions table exists (auto-creates if migration hasn't run yet)
     */
    protected function ensureSubmissionsTable(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('property_submissions')) {
            \Illuminate\Support\Facades\Schema::create('property_submissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('reference')->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('client_name');
                $table->string('contact_phone');
                $table->string('email')->nullable();
                $table->string('title');
                $table->string('property_type')->nullable();
                $table->string('area_name')->nullable();
                $table->decimal('area_sqm', 12, 2)->nullable();
                $table->decimal('price', 15, 2)->nullable();
                $table->decimal('price_per_sqm', 15, 2)->nullable();
                $table->text('property_details')->nullable();
                $table->json('images')->nullable();
                $table->json('pdf_file')->nullable();
                $table->string('status')->default('new')->index();
                $table->string('priority')->default('normal')->index();
                $table->text('notes')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('converted_property_id')->nullable()->constrained('properties')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Handle client property submission (from frontend submit-property form)
     * Stores the submission independently in property_submissions table for review before publishing.
     */
    public function clientSubmit(Request $request)
    {
        $this->ensureSubmissionsTable();

        $request->validate([
            'title'           => 'required|string|max:500',
            'propertyType'    => 'nullable|string|max:255',
            'property_type'   => 'nullable|string|max:255',
            'propertyDetails' => 'nullable|string',
            'property_details'=> 'nullable|string',
            'price'           => 'nullable|numeric|min:0',
            'area'            => 'nullable|numeric|min:0',
            'area_sqft'       => 'nullable|numeric|min:0',
            'areaName'        => 'nullable|string|max:255',
            'area_name'       => 'nullable|string|max:255',
            'clientName'      => 'nullable|string|max:255',
            'contactPhone'    => 'nullable|string|max:50',
            'email'           => 'nullable|string|max:255',
            'userId'          => 'nullable|integer',
            'images.*'        => 'nullable|file|mimes:jpeg,png,jpg,webp,avif|max:20480',
            'pdfFile'         => 'nullable|file|mimes:pdf|max:30720',
        ]);

        $propType = $request->input('propertyType') ?? $request->input('property_type') ?? 'عقار';
        $propDetails = $request->input('propertyDetails') ?? $request->input('property_details') ?? '';
        $area = $request->input('area') ?? $request->input('area_sqft');
        $areaName = $request->input('areaName') ?? $request->input('area_name') ?? 'سوهاج';
        $clientName = $request->input('clientName') ?? $request->input('client_name') ?? 'عميل عبر الموقع';
        $contactPhone = $request->input('contactPhone') ?? $request->input('contact_phone') ?? '';
        $email = $request->input('email') ?? '';
        $price = $request->input('price') ? (float)$request->input('price') : null;
        $areaNum = $area ? (float)$area : null;
        $userId = auth('sanctum')->id() ?? ($request->input('userId') ? (int)$request->input('userId') : null);
        if ($userId && !User::where('id', $userId)->exists()) {
            $userId = null;
        }

        $galleryPaths = [];
        if ($request->hasFile('images')) {
            $uploadedImages = $request->file('images');
            if (!is_array($uploadedImages)) {
                $uploadedImages = [$uploadedImages];
            }
            foreach ($uploadedImages as $img) {
                $path = $img->store('properties/submissions/gallery', 'public');
                $galleryPaths[] = $path;
            }
        }

        $pdfData = null;
        if ($request->hasFile('pdfFile')) {
            $pdf = $request->file('pdfFile');
            $path = $pdf->store('properties/submissions/sources', 'public');
            $pdfData = [
                'name' => $pdf->getClientOriginalName(),
                'path' => $path,
                'size' => $pdf->getSize(),
            ];
        }

        $pricePerSqm = ($price && $areaNum && $areaNum > 0) ? round($price / $areaNum, 2) : null;

        $submission = PropertySubmission::create([
            'user_id'          => $userId,
            'client_name'      => $clientName,
            'contact_phone'    => $contactPhone,
            'email'            => $email ?: null,
            'title'            => $request->input('title'),
            'property_type'    => $propType,
            'area_name'        => $areaName,
            'area_sqm'         => $areaNum,
            'price'            => $price,
            'price_per_sqm'    => $pricePerSqm,
            'property_details' => $propDetails,
            'images'           => $galleryPaths,
            'pdf_file'         => $pdfData,
            'status'           => 'new',
            'priority'         => 'normal',
        ]);

        Log::info('New client property submission received', [
            'submission_id' => $submission->id,
            'reference'     => $submission->reference,
            'client_name'   => $clientName,
            'phone'         => $contactPhone,
        ]);

        return response()->json([
            'success'   => true,
            'id'        => $submission->id,
            'reference' => $submission->reference,
            'message'   => 'تم استلام بيانات العقار بنجاح وسيتم مراجعته والتواصل معكم قريباً',
        ], 201);
    }

    /**
     * List all client property submissions with KPI statistics and filters (Protected)
     */
    public function indexSubmissions(Request $request)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user || (!$user->isAdmin() && !$user->isTeamLead() && !$user->isAgent())) {
            return response()->json(['message' => 'غير مصرح لك بالوصول إلى طلبات عرض العقارات.'], 403);
        }

        $this->ensureSubmissionsTable();

        $query = PropertySubmission::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('contact_phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('area_name', 'like', "%{$search}%")
                  ->orWhere('property_type', 'like', "%{$search}%")
                  ->orWhere('property_details', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($priority = $request->input('priority')) {
            if ($priority !== 'all') {
                $query->where('priority', $priority);
            }
        }

        if ($propertyType = $request->input('property_type')) {
            if ($propertyType !== 'all') {
                $query->where('property_type', 'like', "%{$propertyType}%");
            }
        }

        $submissions = $query->get();

        $stats = [
            'total'      => PropertySubmission::count(),
            'new'        => PropertySubmission::where('status', 'new')->count(),
            'contacted'  => PropertySubmission::where('status', 'contacted')->count(),
            'inspecting' => PropertySubmission::where('status', 'inspecting')->count(),
            'published'  => PropertySubmission::where('status', 'published')->count(),
            'rejected'   => PropertySubmission::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats'   => $stats,
            'data'    => $submissions,
        ]);
    }

    /**
     * Update a client property submission status, priority, notes, etc. (Protected)
     */
    public function updateSubmission(Request $request, $id)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user || (!$user->isAdmin() && !$user->isTeamLead() && !$user->isAgent())) {
            return response()->json(['message' => 'غير مصرح لك بتعديل طلبات عرض العقارات.'], 403);
        }

        $submission = PropertySubmission::findOrFail($id);

        $validated = $request->validate([
            'status'           => 'nullable|string|in:new,contacted,inspecting,published,rejected',
            'priority'         => 'nullable|string|in:low,normal,high,urgent',
            'notes'            => 'nullable|string',
            'assigned_to'      => 'nullable|exists:users,id',
            'title'            => 'nullable|string|max:500',
            'property_type'    => 'nullable|string|max:255',
            'area_name'        => 'nullable|string|max:255',
            'area_sqm'         => 'nullable|numeric|min:0',
            'price'            => 'nullable|numeric|min:0',
            'property_details' => 'nullable|string',
        ]);

        if (isset($validated['price']) || isset($validated['area_sqm'])) {
            $p = $validated['price'] ?? $submission->price;
            $a = $validated['area_sqm'] ?? $submission->area_sqm;
            if ($p && $a && $a > 0) {
                $validated['price_per_sqm'] = round((float)$p / (float)$a, 2);
            }
        }

        $submission->update($validated);

        return response()->json([
            'success'    => true,
            'message'    => 'تم تحديث بيانات طلب عرض العقار بنجاح',
            'submission' => $submission->fresh(['user', 'assignedAgent', 'convertedProperty']),
        ]);
    }

    /**
     * Delete a client property submission (Protected)
     */
    public function destroySubmission($id)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'يحق للمشرف العام فقط حذف طلبات عرض العقارات.'], 403);
        }

        $submission = PropertySubmission::findOrFail($id);
        $submission->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف طلب عرض العقار بنجاح',
        ]);
    }

    /**
     * Approve and convert a client property submission into an official Property in the catalog
     */
    public function convertSubmissionToProperty(Request $request, $id)
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user || (!$user->isAdmin() && !$user->isTeamLead() && !$user->isAgent())) {
            return response()->json(['message' => 'غير مصرح لك بتحويل طلبات عرض العقارات.'], 403);
        }

        $submission = PropertySubmission::findOrFail($id);

        if ($submission->converted_property_id && Property::where('id', $submission->converted_property_id)->exists()) {
            $existingProperty = Property::find($submission->converted_property_id);
            $submission->update(['status' => 'published']);
            return response()->json([
                'success'    => true,
                'already_converted' => true,
                'message'    => 'تم تحويل هذا الطلب لعقار رسمي مسبقاً',
                'property'   => $existingProperty,
                'submission' => $submission->fresh(['user', 'assignedAgent', 'convertedProperty']),
            ]);
        }

        $galleryPaths = is_array($submission->images) ? $submission->images : [];
        $coverPath = $galleryPaths[0] ?? null;
        $sourceFiles = $submission->pdf_file ? [$submission->pdf_file] : [];

        $sourceNotes = "بيانات مالك ومقدم العقار:\nالاسم: {$submission->client_name}\nالهاتف: {$submission->contact_phone}"
            . ($submission->email ? "\nالبريد الإلكتروني: {$submission->email}" : "")
            . ($submission->notes ? "\n\nملاحظات المراجعة والمعاينة:\n{$submission->notes}" : "");

        $nextId = (Property::max('id') ?? 0) + 1;
        $reference = 'Shj' . now()->format('ym') . '-' . $nextId;

        $property = Property::create([
            'title'                 => $submission->title,
            'reference'             => $reference,
            'property_type'         => $submission->property_type ?: 'عقار',
            'purpose'               => $request->input('purpose', 'sale'),
            'property_details'      => $submission->property_details,
            'description'           => $submission->property_details,
            'price'                 => $submission->price,
            'area_sqft'             => $submission->area_sqm,
            'price_per_foot'        => $submission->price_per_sqm,
            'area_name'             => $submission->area_name ?: 'سوهاج',
            'city'                  => 'سوهاج',
            'status'                => $request->input('property_status', 'available'),
            'priority'              => 'medium',
            'cover_image'           => $coverPath,
            'cover_image_watermark' => $coverPath,
            'images'                => $galleryPaths,
            'source_name'           => $submission->client_name,
            'source_notes'          => $sourceNotes,
            'source_files'          => $sourceFiles,
            'company_details'       => "عقار معتمد من طلبات عرض العقارات (#{$submission->reference}) - المالك: {$submission->client_name} - هاتف: {$submission->contact_phone}",
            'created_by_id'         => auth('sanctum')->id() ?? 1,
            'user_id'               => auth('sanctum')->id() ?? 1,
        ]);

        // Record property activity
        PropertyActivity::create([
            'property_id'   => $property->id,
            'user_id'       => auth('sanctum')->id() ?? 1,
            'activity_type' => 'created_from_submission',
            'title'         => 'اعتماد عقار من طلبات اعرض عقارك',
            'description'   => "تم اعتماد ونشر العقار من طلب المالك: {$submission->client_name} ({$submission->reference})",
            'new_data'      => [
                'submission_id'        => $submission->id,
                'submission_reference' => $submission->reference,
                'client_name'          => $submission->client_name,
            ],
        ]);

        $submission->update([
            'status'                => 'published',
            'converted_property_id' => $property->id,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'تم اعتماد العقار ونشره رسمياً في المنصة بنجاح',
            'property'   => $property,
            'submission' => $submission->fresh(['user', 'assignedAgent', 'convertedProperty']),
        ], 201);
    }
}
