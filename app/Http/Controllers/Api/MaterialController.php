<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\ClassRoom;
use App\Models\ClassEnrollment;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * Update material details (Teacher only)
     * 
     * @param Request $request
     * @param int $material_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $material_id)
    {
        // Find the material
        $material = Material::findOrFail($material_id);
        
        // Get the class for this material
        $classRoom = ClassRoom::findOrFail($material->class_id);
        
        // Check if user is a teacher and created the class
        if (Auth::user()->role !== 'Teacher' || $classRoom->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to update materials for this class.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'content' => 'sometimes|required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update material
        if ($request->has('title')) {
            $material->title = $request->title;
        }
        
        if ($request->has('description')) {
            $material->description = $request->description;
        }
        
        if ($request->has('content')) {
            $material->content = $request->content;
        }
        
        $material->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Material updated successfully',
            'data' => [
                'material_id' => $material->material_id,
                'title' => $material->title,
                'description' => $material->description,
                'content' => $material->content,
                'class_id' => $material->class_id,
                'updated_at' => $material->updated_at
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Delete a material (Teacher only)
     * 
     * @param int $material_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($material_id)
    {
        // Find the material
        $material = Material::findOrFail($material_id);
        
        // Get the class for this material
        $classRoom = ClassRoom::findOrFail($material->class_id);
        
        // Check if user is a teacher and created the class
        if (Auth::user()->role !== 'Teacher' || $classRoom->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to delete materials from this class.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete the material
        $material->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Material deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Get material details by material_id
     * 
     * @param int $material_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($material_id)
    {
        try {
            // Validate material_id is numeric
            if (!is_numeric($material_id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid material ID format.'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Check if user is authenticated
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Find the material with classroom relationship (eager loading)
            $material = Material::with('classroom')->find($material_id);
            
            if (!$material) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Material not found.'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Get the classroom for this material - FIXED: using 'classroom' instead of 'class'
            $classRoom = $material->classroom;
            
            // Check if the classroom relationship exists
            if (!$classRoom) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Associated classroom not found.'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Check if user has access to the class
            $hasAccess = false;
            
            if ($user->role === 'Teacher') {
                // Teachers have access if they created the class
                $hasAccess = $classRoom->created_by === $user->id;
            } elseif ($user->role === 'Student') {
                // Students only have access to classes they've enrolled in
                $hasAccess = ClassEnrollment::where('class_id', $material->class_id)
                    ->where('user_id', $user->id)
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
                    'class_name' => $classRoom->name,
                    'created_at' => $material->created_at,
                    'updated_at' => $material->updated_at
                ]
            ], Response::HTTP_OK);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Material not found.'
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while retrieving the material.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all materials for a specific class
     * 
     * @param int $class_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMaterialsInClass($class_id)
    {
        // Find the class
        $classRoom = ClassRoom::findOrFail($class_id);
        
        // Check if user has access to the class
        $hasAccess = false;
        
        if (Auth::user()->role === 'Teacher') {
            // Teachers have access if they created the class
            $hasAccess = $classRoom->created_by === Auth::id();
        } else {
            // Students only have access to classes they've enrolled in
            $hasAccess = ClassEnrollment::where('class_id', $class_id)
                ->where('user_id', Auth::id())
                ->exists();
        }
        
        if (!$hasAccess) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this class.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get all materials for this class
        $materials = Material::where('class_id', $class_id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'class_id' => $class_id,
                'class_name' => $classRoom->name,
                'materials' => $materials->map(function($material) {
                    return [
                        'material_id' => $material->material_id,
                        'title' => $material->title,
                        'description' => $material->description,
                        'created_at' => $material->created_at,
                        'updated_at' => $material->updated_at
                    ];
                })
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Get all materials for a specific class (Student only)
     * 
     * @param int $class_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMaterialsForStudent($class_id)
    {
        // Check if user is a student
        if (Auth::user()->role !== 'Student') {
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for students.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Find the class
        $classRoom = ClassRoom::findOrFail($class_id);
        
        // Check if student is enrolled in this class
        $isEnrolled = ClassEnrollment::where('class_id', $class_id)
            ->where('user_id', Auth::id())
            ->exists();
        
        if (!$isEnrolled) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not enrolled in this class.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get all materials for this class
        $materials = Material::where('class_id', $class_id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'class_id' => $class_id,
                'class_name' => $classRoom->name,
                'materials' => $materials->map(function($material) {
                    return [
                        'material_id' => $material->material_id,
                        'title' => $material->title,
                        'description' => $material->description,
                        'created_at' => $material->created_at,
                        'updated_at' => $material->updated_at
                    ];
                })
            ]
        ], Response::HTTP_OK);
    }
}