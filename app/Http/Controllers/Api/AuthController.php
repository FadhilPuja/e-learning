<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                Password::min(8)
            ],
            'role' => 'required|in:Teacher,Student',
            'phone' => 'nullable|string|max:20',
            'image_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
            'image_url' => $request->image_url,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    /**
     * Login user and create token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Attempt to find user with matching email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect'],
            ]);
        }

        // Remove previous tokens (optional)
        $user->tokens()->delete();

        // Create tokens based on remember_me
        $tokenName = 'auth_token';
        $abilities = ['*'];
        $refreshToken = null;
        
        if ($request->remember_me) {
            // Generate remember token dan simpan ke database
            $rememberTokenValue = \Illuminate\Support\Str::random(100);
            
            // Update user dengan remember token (tidak di-hash untuk testing)
            $user->remember_token = $rememberTokenValue;
            $user->save();
            
            // Atau bisa juga pakai cara ini:
            // $user->update(['remember_token' => $rememberTokenValue]);
            
            // Log untuk debugging
            Log::info('Remember token saved: ' . $rememberTokenValue);
            
            // Create access token (2 hours)
            $token = $user->createToken($tokenName, $abilities, now()->addHours(2))->plainTextToken;
            
            // Create refresh token for 1 day
            $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(1))->plainTextToken;
        } else {
            // Clear remember token if not using remember me
            $user->remember_token = null;
            $user->save();
            
            // Create standard token (1 hour)
            $token = $user->createToken($tokenName, $abilities, now()->addHour())->plainTextToken;
        }

        // Refresh user data from database
        $user->refresh();

        $response = [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'remember_me_status' => $request->remember_me ? 'enabled' : 'disabled'
            ]
        ];

        if ($refreshToken) {
            $response['data']['refresh_token'] = $refreshToken;
        }

        return response()->json($response);
    }

    /**
     * Get the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()
            ]
        ]);
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Delete current access token
        $request->user()->currentAccessToken()->delete();
        
        // Clear remember token
        $user->update(['remember_token' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Refresh access token using refresh token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function remember_me(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the refresh token
            $refreshToken = PersonalAccessToken::findToken($request->refresh_token);

            if (!$refreshToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid refresh token'
                ], 401);
            }

            // Check if token has refresh ability
            if (!$refreshToken->can('refresh')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token does not have refresh capability'
                ], 401);
            }

            // Check if token is expired
            if ($refreshToken->expires_at && $refreshToken->expires_at->isPast()) {
                $refreshToken->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Refresh token has expired'
                ], 401);
            }

            $user = $refreshToken->tokenable;

            // Verify user still has remember token (additional security check)
            if (!$user->remember_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Remember me session has expired'
                ], 401);
            }

            // Delete old access tokens (keep refresh token)
            $user->tokens()->where('name', 'auth_token')->delete();

            // Create new access token (2 hours)
            $newToken = $user->createToken('auth_token', ['*'], now()->addHours(2))->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'user' => $user,
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                    'refresh_token' => $request->refresh_token
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while refreshing token'
            ], 500);
        }
    }
}