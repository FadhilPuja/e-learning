<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\AssignmentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Auth Routes (Public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/remember-me', [AuthController::class, 'remember_me']);
    });
    
    // Protected Routes (Requires Authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // User Routes
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        
        // Class Management Routes
        Route::prefix('classes')->group(function () {
            // Teacher Routes
            Route::post('/', [ClassController::class, 'create']);
            Route::get('/my-classes', [ClassController::class, 'getMyClasses']);
            Route::get('/other-teachers', [ClassController::class, 'getOtherTeachersClasses']);
            Route::post('/{class_id}/materials', [MaterialController::class, 'store']);                        
            Route::get('/{class_id}/class-material', [MaterialController::class,'getMaterialsInClass']);
            Route::post('/{class_id}/assignments', [AssignmentController::class, 'createAssignment']);
            Route::put('/update/{class_id}', [ClassController::class, 'update']);
            Route::delete('/delete/{class_id}', [ClassController::class, 'delete']);
            
            // Student Routes
            Route::get('/available', [ClassController::class, 'getAvailableClasses']);
            Route::get('/enrolled', [ClassController::class, 'getEnrolledClasses']);
            Route::get('/{class_id}/student-material', [MaterialController::class,'getMaterialsForStudent']);
            Route::post('/join', [ClassController::class, 'joinClass']);
            Route::post('/{class_id}/leave', [ClassController::class, 'leaveClass']);
            
            // Common Routes
            Route::get('/{class_id}', [ClassController::class, 'getClassDetails']);        
        });        

        Route::prefix('materials')->group(function () {
            Route::get('/{material_id}', [MaterialController::class, 'show']);
            Route::put('/update/{material_id}', [MaterialController::class, 'update']);
            Route::delete('/delete/{material_id}', [MaterialController::class, 'destroy']);
        });
        
        // Assignment Management Routes
        Route::prefix('assignments')->group(function () {
            // Static routes first (before dynamic parameters)
            Route::get('/class/{class_id}', [AssignmentController::class, 'getClassAssignments']);
            Route::get('/class/{class_id}/student-assignments', [AssignmentController::class, 'getStudentClassAssignments']);
            
            // Dynamic parameter routes
            Route::get('/{assignment_id}', [AssignmentController::class, 'getAssignment']);
            Route::post('/{assignment_id}/submit', [AssignmentController::class, 'submitAssignment']);
            Route::get('/{assignment_id}/submissions', [AssignmentController::class, 'getSubmissions']);
            Route::put('/update/{assignment_id}', [AssignmentController::class, 'updateAssignment']);
            Route::delete('/delete/{assignment_id}', [AssignmentController::class, 'deleteAssignment']);
        });
        
        // Submission Management Routes
        Route::prefix('submissions')->group(function () {            
            Route::post('/{submission_id}/grade', [AssignmentController::class, 'gradeSubmission']);
        });
    });
});