<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SectionController extends Controller
{
    // Public: Get all sections
    public function index()
    {
        $sections = Section::with('subsections')->get();
        return response()->json($sections);
    }

    // Admin Only: Create a new section
    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'section_name' => 'required|string|unique:sections,section_name',
            'p1' => 'nullable|string',
            'p2' => 'nullable|string',
            'p3' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section = Section::create($request->all());

        return response()->json([
            'message' => 'Section created successfully',
            'data' => $section
        ], 201);
    }

    // Public: Get section by ID or Name
    public function show($idOrName)
    {
        $query = Section::with('subsections');

        if (is_numeric($idOrName)) {
            $section = $query->find($idOrName);
        } else {
            $section = $query->where('section_name', $idOrName)->first();
        }

        if (!$section) {
            return response()->json(['message' => 'Section not found'], 404);
        }

        return response()->json($section);
    }

    // Admin Only: Update a section
    public function update(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $section = Section::find($id);

        if (!$section) {
            return response()->json(['message' => 'Section not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'section_name' => 'sometimes|required|string|unique:sections,section_name,' . $id,
            'p1' => 'nullable|string',
            'p2' => 'nullable|string',
            'p3' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section->update($request->all());

        return response()->json([
            'message' => 'Section updated successfully',
            'data' => $section
        ]);
    }

    // Admin Only: Delete a section
    public function destroy(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $section = Section::find($id);

        if (!$section) {
            return response()->json(['message' => 'Section not found'], 404);
        }

        $section->delete();

        return response()->json(['message' => 'Section deleted successfully']);
    }
}
