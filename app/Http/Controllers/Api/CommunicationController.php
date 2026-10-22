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
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        Log::info('Property comment added', [
            'property_id' => $property->id,
            'activity_id' => $activity->id,
            'user_id'     => $user?->id,
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
     * Store a new Lead for unauthenticated users (Contact / Inquiry form)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeLead(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'phone'          => 'required|string|max:50',
            'message'        => 'nullable|string|max:2000',
            'available_time' => 'nullable|string|max:255',
        ]);

        $lead = Lead::create([
            'name'           => $request->input('name'),
            'email'          => $request->input('email'),
            'phone'          => $request->input('phone'),
            'message'        => $request->input('message'),
            'available_time' => $request->input('available_time'),
            'source'         => 'web_contact_form',
            'status'         => 'new',
        ]);

        Log::info('New web lead received', [
            'lead_id' => $lead->id,
            'name'    => $lead->name,
            'phone'   => $lead->phone,
        ]);

        return response()->json([
            'success' => true,
            'result'  => [
                'id' => $lead->id,
            ],
        ]);
    }

    /**
     * Add a new contact (for unauthenticated users)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeContact(Request $request)
    {
        $request->validate([
            'first_name'  => 'required|string|max:255',
            'second_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'required|string|max:50',
        ]);

        $fullName = trim(
            $request->input('first_name') . ' ' .
            ($request->input('second_name') ? $request->input('second_name') . ' ' : '') .
            $request->input('last_name')
        );

        $contact = Lead::create([
            'name'   => $fullName,
            'email'  => $request->input('email'),
            'phone'  => $request->input('phone'),
            'source' => 'web_contact',
            'status' => 'new',
        ]);

        Log::info('New contact stored', [
            'contact_id' => $contact->id,
            'name'       => $fullName,
            'phone'      => $contact->phone,
        ]);

        return response()->json([
            'success'    => true,
            'contact_id' => $contact->id,
        ]);
    }
}
