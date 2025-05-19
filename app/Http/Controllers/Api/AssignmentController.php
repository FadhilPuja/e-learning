<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ClassRoom;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AssignmentController extends Controller
{
    /**
     * Create a new assignment for a class
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $class_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAssignment(Request $request, $class_id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'due_date' => 'nullable|date',
            'file' => 'nullable|file|mimes:pdf,txt,ppt,pptx,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if class exists and user is the teacher
        $classroom = ClassRoom::find($class_id);
        if (!$classroom) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($classroom->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only the teacher who created the class can add assignments'
            ], Response::HTTP_FORBIDDEN);
        }

        // Handle file upload if provided
        $fileUrl = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('assignments', $fileName, 'public');
            $fileUrl = Storage::url($filePath);
        }

        // Create the assignment
        $assignment = Assignment::create([
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'file_url' => $fileUrl,
            'class_id' => $class_id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment created successfully',
            'data' => [
                'assignment_id' => $assignment->assignment_id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'due_date' => $assignment->due_date,
                'file_url' => $assignment->file_url,
                'room_id' => $assignment->class_id,
                'created_at' => $assignment->created_at
            ]
                    ], Response::HTTP_CREATED);
    }

    /**
     * Get details of a specific assignment
     * 
     * @param  int  $assignment_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignment($assignment_id)
    {
        $assignment = Assignment::findOrFail($assignment_id);
        
        // Check if user is enrolled in the class or is the teacher
        $user = Auth::user();
        $classroom = $assignment->classroom;
        
        if ($classroom->created_by !== $user->id && !$classroom->students->contains($user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. You must be enrolled in this class to view assignments'
            ], 403);
        }

        // Get submission if the user is a student
        $submission = null;
        if ($user->role === 'Student') {
            $submission = Submission::where('assignment_id', $assignment_id)
                ->where('user_id', $user->id)
                ->first();
        }

        $responseData = [
            'assignment_id' => $assignment->assignment_id,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'due_date' => $assignment->due_date,
            'file_url' => $assignment->file_url,
            'room_id' => $assignment->class_id,
            'created_at' => $assignment->created_at,
            'updated_at' => $assignment->updated_at,
        ];

        if ($submission) {
            $responseData['submission'] = [
                'submission_id' => $submission->submission_id,
                'file_url' => $submission->file_url,
                'submitted_at' => $submission->submitted_at,
                'status' => $submission->status,
                'score' => $submission->score,
                'feedback' => $submission->feedback
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseData
        ], Response::HTTP_OK);
    }

     /**
     * Get all assignments for a class (for teachers)
     * 
     * @param  int  $class_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassAssignments($class_id)
    {
        $user = Auth::user();
        
        // Check if user is a teacher
        if ($user->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can view all class assignments'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if class exists and teacher owns it
        $classroom = ClassRoom::findOrFail($class_id);
        
        if ($classroom->created_by !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. You must be the teacher of this class to view all assignments'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get all assignments for the class
        $assignments = Assignment::where('class_id', $class_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($assignment) {
                // Count submissions
                $submissionCount = Submission::where('assignment_id', $assignment->assignment_id)->count();
                $gradedCount = Submission::where('assignment_id', $assignment->assignment_id)
                    ->where('status', 'graded')
                    ->count();
                
                return [
                    'assignment_id' => $assignment->assignment_id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'due_date' => $assignment->due_date,
                    'file_url' => $assignment->file_url,
                    'created_at' => $assignment->created_at,
                    'updated_at' => $assignment->updated_at,
                    'submission_stats' => [
                        'total_submissions' => $submissionCount,
                        'graded_submissions' => $gradedCount
                    ]
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'class_name' => $classroom->name,
                'assignments' => $assignments
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Get all assignments for a class (for students)
     * 
     * @param  int  $class_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentClassAssignments($class_id)
    {
        $user = Auth::user();
        
        // Check if user is a student
        if ($user->role !== 'Student') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only students can access this endpoint'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if class exists and student is enrolled
        $classroom = ClassRoom::findOrFail($class_id);
        
        if (!$classroom->students->contains($user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. You must be enrolled in this class to view assignments'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get all assignments for the class
        $assignments = Assignment::where('class_id', $class_id)
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($assignment) use ($user) {
                // Get user's submission for this assignment if exists
                $submission = Submission::where('assignment_id', $assignment->assignment_id)
                    ->where('user_id', $user->id)
                    ->first();
                
                $submissionData = null;
                if ($submission) {
                    $submissionData = [
                        'submission_id' => $submission->submission_id,
                        'file_url' => $submission->file_url,
                        'submitted_at' => $submission->submitted_at,
                        'status' => $submission->status,
                        'score' => $submission->score,
                        'feedback' => $submission->feedback
                    ];
                }
                
                // Check if assignment is overdue
                $isDue = false;
                $isOverdue = false;
                if ($assignment->due_date) {
                    $dueDate = Carbon::parse($assignment->due_date);
                    $now = Carbon::now();
                    $isDue = $now->greaterThan($dueDate);
                    
                    // If due and no submission, mark as overdue
                    if ($isDue && !$submission) {
                        $isOverdue = true;
                    }
                }
                
                return [
                    'assignment_id' => $assignment->assignment_id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'due_date' => $assignment->due_date,
                    'file_url' => $assignment->file_url,
                    'created_at' => $assignment->created_at,
                    'updated_at' => $assignment->updated_at,
                    'is_due' => $isDue,
                    'is_overdue' => $isOverdue,
                    'submission' => $submissionData
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'class_name' => $classroom->name,
                'assignments' => $assignments
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Update an assignment (for teachers)
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $assignment_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAssignment(Request $request, $assignment_id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'file' => 'nullable|file|mimes:pdf,txt,ppt,pptx,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = Auth::user();
        
        // Check if user is a teacher
        if ($user->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can update assignments'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get assignment
        $assignment = Assignment::with('classroom')->findOrFail($assignment_id);
        
        // Check if teacher owns the class
        if ($assignment->classroom->created_by !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to update assignments for this class'
            ], Response::HTTP_FORBIDDEN);
        }

        // Handle file upload if provided
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($assignment->file_url) {
                $oldFilePath = str_replace('/storage/', '', $assignment->file_url);
                Storage::disk('public')->delete($oldFilePath);
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('assignments', $fileName, 'public');
            $assignment->file_url = Storage::url($filePath);
        }

        // Update assignment
        if ($request->has('title')) {
            $assignment->title = $request->title;
        }
        
        if ($request->has('description')) {
            $assignment->description = $request->description;
        }
        
        if ($request->has('due_date')) {
            $assignment->due_date = $request->due_date;
        }
        
        $assignment->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment updated successfully',
            'data' => [
                'assignment_id' => $assignment->assignment_id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'due_date' => $assignment->due_date,
                'file_url' => $assignment->file_url,
                'room_id' => $assignment->class_id,
                'updated_at' => $assignment->updated_at
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Delete an assignment (for teachers)
     * 
     * @param  int  $assignment_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAssignment($assignment_id)
    {
        $user = Auth::user();
        
        // Check if user is a teacher
        if ($user->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can delete assignments'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get assignment
        $assignment = Assignment::with('classroom')->findOrFail($assignment_id);
        
        // Check if teacher owns the class
        if ($assignment->classroom->created_by !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to delete assignments for this class'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if there are submissions
        $submissionsExist = Submission::where('assignment_id', $assignment_id)->exists();
        
        if ($submissionsExist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete assignment with existing submissions'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Delete file if exists
        if ($assignment->file_url) {
            $filePath = str_replace('/storage/', '', $assignment->file_url);
            Storage::disk('public')->delete($filePath);
        }

        // Delete assignment
        $assignment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Submit an assignment (for students)
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $assignment_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitAssignment(Request $request, $assignment_id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,txt,ppt,pptx,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Check if user is a student
        if ($user->role !== 'Student') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only students can submit assignments'
            ], 403);
        }

        // Check if assignment exists
        $assignment = Assignment::findOrFail($assignment_id);
        $classroom = $assignment->classroom;

        // Check if student is enrolled in the class
        if (!$classroom->students->contains($user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not enrolled in this class'
            ], 403);
        }

        // Check if due date has passed
        if ($assignment->due_date && now()->greaterThan($assignment->due_date)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The due date for this assignment has passed'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Handle file upload
        $file = $request->file('file');
        $fileName = time() . '_' . $user->id . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('submissions', $fileName, 'public');
        $fileUrl = Storage::url($filePath);
        
        // Define current time for submitted_at
        $currentTime = Carbon::now();

        // Check if student has already submitted
        $existingSubmission = Submission::where('assignment_id', $assignment_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingSubmission) {
            // Update existing submission
            $existingSubmission->file_url = $fileUrl;
            $existingSubmission->submitted_at = $currentTime;
            $existingSubmission->status = 'pending';
            $existingSubmission->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment resubmitted successfully',
                'data' => [
                    'submission_id' => $existingSubmission->submission_id,
                    'assignment_id' => $assignment_id,
                    'file_url' => $fileUrl,
                    'submitted_at' => $existingSubmission->submitted_at
                ]
            ], 200);
        }

        // Create new submission
        $submission = Submission::create([
            'assignment_id' => $assignment_id,
            'user_id' => $user->id,
            'file_url' => $fileUrl,
            'status' => 'pending',
            'submitted_at' => $currentTime
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment submitted successfully',
            'data' => [
                'submission_id' => $submission->submission_id,
                'assignment_id' => $assignment_id,
                'file_url' => $fileUrl,
                'submitted_at' => $submission->submitted_at
            ]
        ], 201);
    }

    /**
     * Grade a submission (for teachers)
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $submission_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function gradeSubmission(Request $request, $submission_id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|min:0|max:100',
            'feedback' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Check if user is a teacher
        if ($user->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can grade submissions'
            ], 403);
        }

        // Get submission
        $submission = Submission::with('assignment.classroom', 'student')->findOrFail($submission_id);
        
        // Check if teacher owns the class
        if ($submission->assignment->classroom->created_by !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to grade submissions for this class'
            ], 403);
        }

        // Update submission
        $submission->score = $request->score;
        $submission->feedback = $request->feedback;
        $submission->status = 'graded';
        $submission->graded_at = Carbon::now();
        $submission->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Submission graded successfully',
            'data' => [
                'submission_id' => $submission->submission_id,
                'assignment_id' => $submission->assignment_id,
                'student_name' => $submission->student->name,
                'score' => $submission->score,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at
            ]
        ], 200);
    }

    /**
     * Get all submissions for an assignment (for teachers)
     * 
     * @param  int  $assignment_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubmissions($assignment_id)
    {
        $user = Auth::user();
        
        // Check if user is a teacher
        if ($user->role !== 'Teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only teachers can view all submissions'
            ], 403);
        }

        // Check if assignment exists
        $assignment = Assignment::with('classroom')->findOrFail($assignment_id);
        
        // Check if teacher owns the class
        if ($assignment->classroom->created_by !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to view submissions for this class'
            ], 403);
        }

        // Get all submissions
        $submissions = Submission::where('assignment_id', $assignment_id)
            ->with('student:id,name')
            ->get()
            ->map(function ($submission) {
                return [
                    'submission_id' => $submission->submission_id,
                    'student_name' => $submission->student->name,
                    'file_url' => $submission->file_url,
                    'submitted_at' => $submission->submitted_at,
                    'status' => $submission->status,
                    'score' => $submission->score,
                    'feedback' => $submission->feedback
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $submissions
        ], 200);
    }
}