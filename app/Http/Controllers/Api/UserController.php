<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get all users / team members listing (for user selection modal & general management)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Exclude users with the admin role
        $query->where('role', '!=', 'admin');

        // Optional search by name, email, designation or department
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        $users = $query->select([
            'id',
            'name',
            'email',
            'mobile_number',
            'phone',
            'department',
            'designation',
            'employee_id',
            'date_of_joining',
            'avatar',
            'status',
            'created_at',
        ])->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Users list retrieved successfully.',
            'total' => $users->count(),
            'data' => $users,
        ], 200);
    }
}
