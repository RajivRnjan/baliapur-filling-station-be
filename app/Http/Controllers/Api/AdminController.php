<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\StaffCreatedMail;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can view admin/staff list.'
                ], 403);
            }

            $admins = User::whereIn('role', ['ADMIN', 'STAFF'])->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'admins' => $admins
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin list.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can create new admins.'
                ], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'nullable|in:ADMIN,SUPER_ADMIN,STAFF',
                'phone' => 'nullable|string|max:20',
                'salary' => 'nullable|numeric|min:0',
            ]);

            $admin = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? 'ADMIN',
                'phone' => $request->phone,
                'salary' => $request->salary ?? 0.00,
            ]);

            try {
                Mail::to($admin->email)->send(new StaffCreatedMail($admin, $request->password));
            } catch (\Exception $e) {
                Log::error('Failed to send welcome email: ' . $e->getMessage());
                // We still want to return success for the account creation even if email fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'admin' => $admin
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Admin Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the account.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can update admins.'
                ], 403);
            }

            $admin = User::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'nullable|string|min:8',
                'role' => 'nullable|in:ADMIN,SUPER_ADMIN,STAFF',
                'phone' => 'nullable|string|max:20',
                'salary' => 'nullable|numeric|min:0',
            ]);

            $admin->name = $request->name;
            $admin->email = $request->email;
            if ($request->role) {
                $admin->role = $request->role;
            }
            if ($request->password) {
                $admin->password = Hash::make($request->password);
            }
            if ($request->has('phone')) {
                $admin->phone = $request->phone;
            }
            if ($request->has('salary')) {
                $admin->salary = $request->salary;
            }
            
            $admin->save();

            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'admin' => $admin
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Admin Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the account.'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $admin = User::where('id', $id)->whereIn('role', ['ADMIN', 'STAFF'])->firstOrFail();
            $admin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the record.'
            ], 500);
        }
    }
}
