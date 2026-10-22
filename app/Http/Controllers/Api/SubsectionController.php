<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subsection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubsectionController extends Controller
{
    // Public: Get all subsections (optional, maybe filtered by section)
    public function index(Request $request)
    {
        $query = Subsection::query();

        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        if ($request->has('section_name')) {
            $query->whereHas('section', function($q) use ($request) {
                $q->where('section_name', $request->section_name);
            });
        }

        $subsections = $query->with('section')->get();
        return response()->json($subsections);
    }

    // Admin Only: Create a new subsection
    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'section_id' => 'required|exists:sections,id',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subsection = Subsection::create($request->all());

        return response()->json([
            'message' => 'Subsection created successfully',
            'data' => $subsection
        ], 201);
    }

    // Public: Show a specific subsection
    public function show($id)
    {
        $subsection = Subsection::with('section')->find($id);

        if (!$subsection) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        return response()->json($subsection);
    }

    // Admin Only: Update a subsection
    public function update(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $subsection = Subsection::find($id);

        if (!$subsection) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'section_id' => 'sometimes|required|exists:sections,id',
            'content' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subsection->update($request->all());

        return response()->json([
            'message' => 'Subsection updated successfully',
            'data' => $subsection
        ]);
    }

    // Admin Only: Delete a subsection
    public function destroy(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $subsection = Subsection::find($id);

        if (!$subsection) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        $subsection->delete();

        return response()->json(['message' => 'Subsection deleted successfully']);
    }
}
