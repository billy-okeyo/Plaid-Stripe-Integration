<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LendingUser;
use Illuminate\Support\Facades\Log;
use Exception;

class UserController extends Controller
{
    /**
     * Create a new lending user
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_type' => 'required|in:lender,borrower',
                'email' => 'required|email|unique:lending_users',
                'business_name' => 'required|string|max:255',
                'phone' => 'required|string|max:20'
            ]);

            $user = LendingUser::create([
                'user_type' => $validated['user_type'],
                'email' => $validated['email'],
                'business_name' => $validated['business_name'],
                'phone' => $validated['phone'],
                'country' => 'US',
                'status' => 'active',
                'kyc_status' => 'approved'
            ]);

            Log::info('User created successfully', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'success' => true,
                'user' => $user,
                'message' => 'User created successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('User creation validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('User creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all lending users
     */
    public function index()
    {
        try {
            $users = LendingUser::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'users' => $users
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch users', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    /**
     * Get a specific user
     */
    public function show($id)
    {
        try {
            $user = LendingUser::findOrFail($id);

            return response()->json([
                'success' => true,
                'user' => $user
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Update a user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = LendingUser::findOrFail($id);

            $validated = $request->validate([
                'business_name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'status' => 'sometimes|in:pending_verification,active,suspended,inactive',
                'kyc_status' => 'sometimes|in:pending,approved,rejected,requires_info'
            ]);

            $user->update($validated);

            Log::info('User updated successfully', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'user' => $user,
                'message' => 'User updated successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user'
            ], 500);
        }
    }
}
