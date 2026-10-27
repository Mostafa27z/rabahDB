<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommunicationController extends Controller
{
    /**
     * Add a comment to a Property timeline
     */
    public function addComment(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer',
            'comment'   => 'required|string|max:2000',
        ]);

        $entityId = $request->input('entity_id');
        $content = $request->input('comment');

        $user = auth()->user();
        $userName = $user->name ?? 'مستخدم';
        $userEmail = $user->email ?? '';
        $userPhone = $user->phone ?? '';

        $fullComment = "{$userName}" . ($userPhone ? " ({$userPhone})" : "") . ": {$content}";

        $property = Property::find($entityId);
        if (!$property) {
            return response()->json([
                'success' => false,
                'error'   => 'Property not found',
            ], 404);
        }

        $activity = PropertyActivity::create([
            'property_id'   => $property->id,
            'user_id'       => $user?->id ?? null,
            'activity_type' => 'comment',
            'title'         => 'تعليق جديد',
            'description'   => $fullComment,
            'new_data'      => [
                'comment'    => $content,
                'author'     => $userName,
                'email'      => $userEmail,
                'phone'      => $userPhone,
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'result'  => [
                'id'      => $activity->id,
                'comment' => $content,
            ],
        ]);
    }

    /**
     * Store a new Lead for unauthenticated users (Property Search Consultation / Inquiry form)
     */
    public function storeLead(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|max:50',
            'email'         => 'nullable|string|max:255',
            'location'      => 'nullable|string|max:255',
            'property_type' => 'nullable|string|max:255',
            'message'       => 'nullable|string|max:2000',
        ]);

        $lead = Lead::create([
            'name'          => $request->input('name'),
            'email'         => $request->input('email') ?: null,
            'phone'         => $request->input('phone'),
            'location'      => $request->input('location') ?: null,
            'property_type' => $request->input('property_type') ?: null,
            'message'       => $request->input('message') ?: null,
            'available_time'=> null,
            'source'        => 'web_search_form',
            'status'        => 'new',
            'priority'      => 'normal',
        ]);

        Log::info('New web search lead received', [
            'lead_id' => $lead->id,
            'name'    => $lead->name,
            'phone'   => $lead->phone,
        ]);

        return response()->json([
            'success' => true,
            'lead'    => $lead,
        ], 201);
    }

    /**
     * Add a new contact message (Contact Us page form)
     */
    public function storeContact(Request $request)
    {
        $request->validate([
            'first_name'  => 'nullable|string|max:255',
            'second_name' => 'nullable|string|max:255',
            'last_name'   => 'nullable|string|max:255',
            'name'        => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'required|string|max:50',
            'message'     => 'nullable|string|max:2000',
            'subject'     => 'nullable|string|max:255',
        ]);

        $fullName = $request->input('name');
        if (!$fullName) {
            $fullName = trim(
                ($request->input('first_name') ?? '') . ' ' .
                ($request->input('second_name') ? $request->input('second_name') . ' ' : '') .
                ($request->input('last_name') ?? '')
            );
        }
        if (!$fullName) {
            $fullName = 'عميل مهتم';
        }

        $lead = Lead::create([
            'name'          => $fullName,
            'email'         => $request->input('email') ?: null,
            'phone'         => $request->input('phone'),
            'message'       => $request->input('message') ?: $request->input('subject'),
            'source'        => 'web_contact_form',
            'status'        => 'new',
            'priority'      => 'normal',
        ]);

        Log::info('New contact form lead received', [
            'lead_id' => $lead->id,
            'name'    => $fullName,
            'phone'   => $lead->phone,
        ]);

        return response()->json([
            'success' => true,
            'lead'    => $lead,
        ], 201);
    }

    /**
     * Get all leads with filtering, search, and dashboard statistics (Protected)
     */
    public function indexLeads(Request $request)
    {
        $query = Lead::query()->latest();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('property_type', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Source filter
        if ($source = $request->input('source')) {
            if ($source !== 'all') {
                $query->where('source', $source);
            }
        }

        // Priority filter
        if ($priority = $request->input('priority')) {
            if ($priority !== 'all') {
                $query->where('priority', $priority);
            }
        }

        $leads = $query->get();

        // Calculate KPI Stats
        $stats = [
            'total'       => Lead::count(),
            'new'         => Lead::where('status', 'new')->count(),
            'contacted'   => Lead::where('status', 'contacted')->count(),
            'negotiation' => Lead::where('status', 'negotiation')->count(),
            'converted'   => Lead::where('status', 'converted')->count(),
            'lost'        => Lead::where('status', 'lost')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats'   => $stats,
            'data'    => $leads,
        ]);
    }

    /**
     * Update lead status and details (Protected)
     */
    public function updateLead(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'status'        => 'nullable|string|in:new,contacted,negotiation,converted,lost',
            'notes'         => 'nullable|string',
            'priority'      => 'nullable|string|in:low,normal,high,urgent',
            'assigned_to'   => 'nullable|exists:users,id',
            'location'      => 'nullable|string|max:255',
            'property_type' => 'nullable|string|max:255',
        ]);

        $lead->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الطلب بنجاح',
            'lead'    => $lead->load('assignedAgent'),
        ]);
    }

    /**
     * Delete a lead (Protected)
     */
    public function destroyLead($id)
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الطلب بنجاح',
        ]);
    }
}
