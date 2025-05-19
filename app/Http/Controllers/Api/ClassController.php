<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\ClassEnrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class ClassController extends Controller
{
    /**
     * Create a new class (Teacher only)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        // Validate user is a teacher
        if (Auth::user()->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can create classes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        // Generate a unique code
        $uniqueCode = $this->generateUniqueCode();

        // Create the class
        $class = ClassRoom::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'unique_code' => $uniqueCode,
            'created_by' => Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Class created successfully',
            'data' => [
                'class_id' => $class->class_id,
                'name' => $class->name,
                'description' => $class->description,
                'unique_code' => $class->unique_code,
                'created_by' => $class->created_by,
                'created_at' => $class->created_at
            ]
        ], Response::HTTP_CREATED);
    }

        /**
     * Update an existing class (Teacher only)
     * 
     * @param Request $request
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $classId)
    {
        // Validate user is a teacher
        if (Auth::user()->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can update classes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Find the class
        $class = ClassRoom::find($classId);

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if the teacher owns this class
        if ($class->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only update your own classes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string'
        ]);

        // Update the class
        $class->update([
            'name' => $validated['name'] ?? $class->name,
            'description' => $validated['description'] ?? $class->description
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Class updated successfully',
            'data' => [
                'class_id' => $class->class_id,
                'name' => $class->name,
                'description' => $class->description,
                'unique_code' => $class->unique_code,
                'created_by' => $class->created_by,
                'updated_at' => $class->updated_at
            ]
        ]);
    }

    /**
     * Delete a class (Teacher only)
     * 
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($classId)
    {
        // Validate user is a teacher
        if (Auth::user()->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can delete classes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Find the class
        $class = ClassRoom::find($classId);

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if the teacher owns this class
        if ($class->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only delete your own classes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete the class (this should cascade delete enrollments if set up in the migration)
        $class->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Class deleted successfully'
        ]);
    }

    /**
     * Get all classes created by the authenticated teacher
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyClasses()
    {
        // Validate user is a teacher
        if (Auth::user()->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can access their classes'
            ], Response::HTTP_FORBIDDEN);
        }

        $classes = ClassRoom::where('created_by', Auth::id())
            ->withCount('students as student_count')
            ->get()
            ->map(function ($class) {
                return [
                    'class_id' => $class->class_id,
                    'name' => $class->name,
                    'description' => $class->description,
                    'unique_code' => $class->unique_code,
                    'student_count' => $class->student_count,
                    'created_at' => $class->created_at
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $classes
        ]);
    }

    /**
     * Get classes created by other teachers
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOtherTeachersClasses()
    {
        // Validate user is a teacher
        if (Auth::user()->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can access this endpoint'
            ], Response::HTTP_FORBIDDEN);
        }

        $classes = ClassRoom::where('created_by', '!=', Auth::id())
            ->with('teacher:id,name')
            ->get()
            ->map(function ($class) {
                return [
                    'class_id' => $class->class_id,
                    'name' => $class->name,
                    'description' => $class->description,
                    'teacher_name' => $class->teacher->name,
                    'created_at' => $class->created_at
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $classes
        ]);
    }

    /**
     * Get all available classes (Student only)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableClasses()
    {
        // Validate user is a student
        if (Auth::user()->role !== 'Student') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only students can access available classes'
            ], Response::HTTP_FORBIDDEN);
        }

        $userId = Auth::id();

        $classes = ClassRoom::with('teacher:id,name')
            ->select('classes.*')
            ->selectSub(function ($query) use ($userId) {
                $query->selectRaw('COUNT(*)')
                    ->from('class_enrollments')
                    ->whereColumn('class_enrollments.class_id', 'classes.class_id')
                    ->where('class_enrollments.user_id', $userId);
            }, 'is_joined')
            ->get()
            ->map(function ($class) {
                return [
                    'class_id' => $class->class_id,
                    'name' => $class->name,
                    'description' => $class->description,
                    'teacher_name' => $class->teacher->name,
                    'is_joined' => (bool) $class->is_joined,
                    'created_at' => $class->created_at
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $classes
        ]);
    }

    /**
     * Get classes that the student has enrolled in
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEnrolledClasses()
    {
        // Validate user is a student
        if (Auth::user()->role !== 'Student') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only students can access enrolled classes'
            ], Response::HTTP_FORBIDDEN);
        }

        $enrolledClasses = User::find(Auth::id())
            ->belongsToMany(ClassRoom::class, 'class_enrollments', 'user_id', 'class_id')
            ->withPivot('enrolled_at')
            ->with(['teacher:id,name', 'rooms'])
            ->get()
            ->map(function ($class) {
                return [
                    'class_id' => $class->class_id,
                    'name' => $class->name,
                    'description' => $class->description,
                    'teacher_name' => $class->teacher->name,
                    'enrolled_at' => $class->pivot->enrolled_at,
                    'room_count' => $class->rooms->count()
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $enrolledClasses
        ]);
    }

    /**
     * Join a class using the unique code (Student only)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function joinClass(Request $request)
    {
        // Validate user is a student
        if (Auth::user()->role !== 'Student') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only students can join classes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate the request
        $request->validate([
            'unique_code' => 'required|string|exists:classes,unique_code'
        ]);

        $class = ClassRoom::where('unique_code', $request->unique_code)
            ->with('teacher:id,name')
            ->first();

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if already enrolled
        $alreadyEnrolled = ClassEnrollment::where('class_id', $class->class_id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already enrolled in this class'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create enrollment
        ClassEnrollment::create([
            'class_id' => $class->class_id,
            'user_id' => Auth::id(),
            'enrolled_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully joined the class',
            'data' => [
                'class_id' => $class->class_id,
                'name' => $class->name,
                'description' => $class->description,
                'teacher_name' => $class->teacher->name
            ]
        ]);
    }

    /**
     * Leave a class that the student has enrolled in (Student only)
     * 
     * @param int $classId The ID of the class to leave
     * @return \Illuminate\Http\JsonResponse
     */
    public function leaveClass($classId)
    {
        // Validate that the authenticated user is a student
        if (Auth::user()->role !== 'Student') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only students can leave classes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if the specified class exists
        $class = ClassRoom::find($classId);
        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if the student is actually enrolled in this class
        $enrollment = ClassEnrollment::where('class_id', $classId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not enrolled in this class'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Delete the enrollment record to leave the class
        $enrollment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully left the class'
        ]);
    }

    /**
     * Get class details
     * 
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassDetails($classId)
    {
        $class = ClassRoom::with(['teacher:id,name', 'rooms:room_id,class_id,name,description'])
            ->find($classId);

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user has access to the class
        $hasAccess = false;
        
        if (Auth::user()->role === 'Teacher') {
            // Teachers have access to all classes
            $hasAccess = true;
        } else {
            // Students only have access to classes they've enrolled in
            $hasAccess = ClassEnrollment::where('class_id', $classId)
                ->where('user_id', Auth::id())
                ->exists();
        }

        if (!$hasAccess) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this class'
            ], Response::HTTP_FORBIDDEN);
        }

        // Format rooms data
        $rooms = $class->rooms->map(function ($room) {
            return [
                'room_id' => $room->room_id,
                'name' => $room->name,
                'description' => $room->description
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'class_id' => $class->class_id,
                'name' => $class->name,
                'description' => $class->description,
                'unique_code' => $class->unique_code,
                'teacher_name' => $class->teacher->name,
                'created_at' => $class->created_at,
                'rooms' => $rooms
            ]
        ]);
    }

    /**
     * Generate a unique code for the class
     * 
     * @return string
     */
    private function generateUniqueCode()
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (ClassRoom::where('unique_code', $code)->exists());
        
        return $code;
    }
}