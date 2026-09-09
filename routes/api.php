<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AssignedShiftController;
use App\Http\Controllers\Api\DesignationController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\ShiftRotationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin / Manager API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {
    // Public routes
    Route::post('/signup', [AdminAuthController::class, 'signup']);
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password', [AdminAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AdminAuthController::class, 'resetPassword']);

    // Protected routes (Sanctum Auth Token Required)
    Route::middleware('auth:sanctum')->group(function () {
        // Profile routes
        Route::get('/profile', [AdminAuthController::class, 'getProfile']);
        Route::put('/profile', [AdminAuthController::class, 'updateProfile']);
        Route::post('/update-profile', [AdminAuthController::class, 'updateProfile']);
        Route::post('/update-password', [AdminAuthController::class, 'updatePassword']);
        Route::put('/update-password', [AdminAuthController::class, 'updatePassword']);

        // Users route (for role assignment modal / user selection)
        Route::get('/users', [UserController::class, 'index']);

        // Designations
        Route::get('/designations/search', [DesignationController::class, 'search']);
        Route::get('/designations', [DesignationController::class, 'index']);
        Route::post('/designations', [DesignationController::class, 'store']);
        Route::get('/designations/{id}', [DesignationController::class, 'show']);
        Route::delete('/designations/{id}/employees/{employeeId}', [DesignationController::class, 'removeEmployee']);

        // Employees (stored in users with role=employee)
        Route::get('/employees/search', [EmployeeController::class, 'search']);
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [EmployeeController::class, 'show']);
        Route::match(['put', 'patch'], '/employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);

        // Permission APIs (Requirement 2 & Screenshot 1)
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{role_id}', [PermissionController::class, 'index']);
        Route::get('/roles/{id}/permissions', [PermissionController::class, 'index']);

        // Role Permission Assignment APIs (Requirement 3 & Screenshot 1)
        Route::post('/roles/assign-permissions', [RoleController::class, 'assignPermissions']);
        Route::post('/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);

        // Remove User from Role APIs (Requirement 1 & Screenshot 2)
        Route::delete('/roles/{id}/users/{userId}', [RoleController::class, 'removeUser']);
        Route::post('/roles/{id}/remove-user', [RoleController::class, 'removeUser']);
        Route::delete('/roles/{id}/remove-user', [RoleController::class, 'removeUser']);

        // Role CRUD & Listing APIs (Requirements 4 & 5, Screenshots 2 & 3)
        Route::get('/roles/search', [RoleController::class, 'search']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::post('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

        // Requirement 5: Shift Management Overview & Statistics (Screenshot 4)
        Route::get('/shifts/overview', [ShiftController::class, 'overview']);
        Route::get('/shift-management/overview', [ShiftController::class, 'overview']);

        // Requirement 2: Shift CRUD APIs (Screenshot 1)
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::get('/shifts/{id}', [ShiftController::class, 'show']);
        Route::put('/shifts/{id}', [ShiftController::class, 'update']);
        Route::post('/shifts/{id}', [ShiftController::class, 'update']);
        Route::delete('/shifts/{id}', [ShiftController::class, 'destroy']);

        // Requirement 3: Assign Shift APIs (Screenshot 2)
        Route::get('/assigned-shifts', [AssignedShiftController::class, 'index']);
        Route::post('/assigned-shifts', [AssignedShiftController::class, 'store']);
        Route::get('/assigned-shifts/{id}', [AssignedShiftController::class, 'show']);
        Route::put('/assigned-shifts/{id}', [AssignedShiftController::class, 'update']);
        Route::post('/assigned-shifts/{id}', [AssignedShiftController::class, 'update']);
        Route::delete('/assigned-shifts/{id}', [AssignedShiftController::class, 'destroy']);

        // Requirement 4: Shift Rotation APIs (Screenshot 3 - Parts 1, 2, 3)
        Route::get('/shift-rotations', [ShiftRotationController::class, 'index']);
        Route::post('/shift-rotations', [ShiftRotationController::class, 'store']);
        Route::get('/shift-rotations/{id}', [ShiftRotationController::class, 'show']);
        Route::put('/shift-rotations/{id}', [ShiftRotationController::class, 'update']);
        Route::post('/shift-rotations/{id}', [ShiftRotationController::class, 'update']);
        Route::post('/shift-rotations/{id}/assign-users', [ShiftRotationController::class, 'assignUsers']);
        Route::delete('/shift-rotations/{id}/users/{userId}', [ShiftRotationController::class, 'removeUser']);
        Route::post('/shift-rotations/{id}/remove-user', [ShiftRotationController::class, 'removeUser']);
        Route::delete('/shift-rotations/{id}/remove-user', [ShiftRotationController::class, 'removeUser']);
        Route::delete('/shift-rotations/{id}', [ShiftRotationController::class, 'destroy']);

        // Auth
        Route::post('/logout', [AdminAuthController::class, 'logout']);
    });
});
