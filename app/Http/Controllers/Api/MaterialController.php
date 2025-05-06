<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\ClassRoom;
use App\Models\ClassEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

class MaterialController extends Controller
{
    /**
     * Create a new material for a specific class (Teacher only)
     * 
     * @param Request $request
     * @param int $class_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $class_id)
    {
        // Check if user is a teacher
        if (Auth::user()->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only teachers can create materials.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Find the class
        $classRoom = ClassRoom::findOrFail($class_id);
        
        // Check if the authenticated user is the teacher of this class
        if ($classRoom->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to add materials to this class.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create material
        $material = new Material();
        $material->title = $request->title;
        $material->description = $request->description;
        $material->content = $request->content;
        $material->class_id = $class_id;
        $material->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Material created successfully',
            'data' => [
                'material_id' => $material->material_id,
                'title' => $material->title,
                'description' => $material->description,
                'content' => $material->content,
                'class_id' => $material->class_id,
                'created_at' => $material->created_at
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Get material details by material_id
     * 
     * @param int $material_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($material_id)
    {
        // Find the material
        $material = Material::findOrFail($material_id);
        
        // Get the class for this material
        $classRoom = ClassRoom::findOrFail($material->class_id);
        
        // Check if user has access to the class
        $hasAccess = false;
        
        if (Auth::user()->role === 'Teacher') {
            // Teachers have access if they created the class
            $hasAccess = $classRoom->created_by === Auth::id();
        } else {
            // Students only have access to classes they've enrolled in
            $hasAccess = ClassEnrollment::where('class_id', $material->class_id)
                ->where('user_id', Auth::id())
                ->exists();
        }
        
        if (!$hasAccess) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this material.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'material_id' => $material->material_id,
                'title' => $material->title,
                'description' => $material->description,
                'content' => $material->content,
                'class_id' => $material->class_id,
                'created_at' => $material->created_at,
                'updated_at' => $material->updated_at
            ]
        ], Response::HTTP_OK);
    }
}