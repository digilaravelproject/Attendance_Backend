<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AssignedShiftController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DesignationManagementController;
use App\Http\Controllers\Api\EmployeeAttendanceController;
use App\Http\Controllers\Api\EmployeeManagementController;
use App\Http\Controllers\Api\LeaveManagementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ShiftManagementController;
use App\Http\Controllers\Api\ShiftRotationController;
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

    // Shared authenticated routes for administrators and employees.
    Route::middleware(['auth:sanctum', 'track.admin.action'])->group(function () {
        Route::get('/profile', [AdminAuthController::class, 'getProfile']);
        Route::put('/profile', [AdminAuthController::class, 'updateProfile']);
        Route::post('/update-profile', [AdminAuthController::class, 'updateProfile']);
        Route::post('/update-password', [AdminAuthController::class, 'updatePassword']);
        Route::put('/update-password', [AdminAuthController::class, 'updatePassword']);
        Route::delete('/documents/{document}', [AdminAuthController::class, 'deleteDocument']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        // Role-aware dashboard: employee payload remains unchanged; admins receive management totals.
        Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);

        // The authenticated user's notifications (works for both admin and employee tokens).
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications', [NotificationController::class, 'destroyAll']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show'])->whereNumber('id');
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id');

        // Employee-friendly aliases for clients that keep admin and employee services separate.
        Route::get('/employee-notifications', [NotificationController::class, 'index']);
        Route::get('/employee-notifications/{id}', [NotificationController::class, 'show'])->whereNumber('id');
        Route::patch('/employee-notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::delete('/employee-notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id');

        Route::get('/leave-approvers', [LeaveManagementController::class, 'approvers']);
        Route::get('/leave-requests/calendar', [LeaveManagementController::class, 'calendar']);
        Route::get('/leave-requests', [LeaveManagementController::class, 'index']);
        Route::post('/leave-requests', [LeaveManagementController::class, 'store']);
        Route::get('/leave-requests/{id}', [LeaveManagementController::class, 'show'])->whereNumber('id');
        Route::post('/leave-requests/{id}/approve', [LeaveManagementController::class, 'approve'])->whereNumber('id');
        Route::post('/leave-requests/{id}/reject', [LeaveManagementController::class, 'reject'])->whereNumber('id');
        Route::get('/leave-types', [LeaveManagementController::class, 'leaveTypes']);
        Route::get('/holidays', [LeaveManagementController::class, 'holidays']);
    });

    // Employee panel routes use the same /api/admin authorization endpoints and token.
    Route::middleware(['auth:sanctum', 'employee'])->group(function () {
        Route::post('/attendance/check-in', [EmployeeAttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [EmployeeAttendanceController::class, 'checkOut']);
        Route::post('/attendance/mark', [EmployeeAttendanceController::class, 'checkIn']);
        Route::post('/attendance/mark-logout', [EmployeeAttendanceController::class, 'checkOut']);
        Route::get('/birthdays/upcoming', [EmployeeAttendanceController::class, 'upcomingBirthdays']);
        Route::get('/attendance/history', [EmployeeAttendanceController::class, 'history']);
    });

    // Protected routes (Sanctum Auth Token Required)
    Route::middleware(['auth:sanctum', 'admin', 'track.admin.action'])->group(function () {
        // Admin dashboard module counts and employee-notification administration.
        Route::get('/modules/statistics', [AdminDashboardController::class, 'moduleStatistics']);
        Route::get('/module-statistics', [AdminDashboardController::class, 'moduleStatistics']);
        Route::get('/employees/{employeeId}/notifications', [NotificationController::class, 'employeeIndex'])->whereNumber('employeeId');
        Route::get('/employees/{employeeId}/notifications/{id}', [NotificationController::class, 'employeeShow'])->whereNumber(['employeeId', 'id']);
        Route::patch('/employees/{employeeId}/notifications/{id}/read', [NotificationController::class, 'employeeMarkRead'])->whereNumber(['employeeId', 'id']);
        Route::delete('/employees/{employeeId}/notifications/{id}', [NotificationController::class, 'employeeDestroy'])->whereNumber(['employeeId', 'id']);

        // Designations
        Route::get('/designations/search', [DesignationManagementController::class, 'search']);
        Route::get('/designations', [DesignationManagementController::class, 'index']);
        Route::post('/designations', [DesignationManagementController::class, 'store']);
        Route::get('/designations/{id}', [DesignationManagementController::class, 'show']);
        Route::post('/designations/{id}/employees', [DesignationManagementController::class, 'assignEmployees']);
        Route::match(['put', 'patch'], '/designations/{id}', [DesignationManagementController::class, 'update']);
        Route::delete('/designations/{id}', [DesignationManagementController::class, 'destroy']);
        Route::delete('/designations/{id}/employees/{employeeId}', [DesignationManagementController::class, 'removeEmployee']);

        // Departments
        Route::get('/departments/search', [DepartmentController::class, 'search']);
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::get('/departments/{id}', [DepartmentController::class, 'show']);
        Route::match(['put', 'patch'], '/departments/{id}', [DepartmentController::class, 'update']);
        Route::post('/departments/{id}/employees', [DepartmentController::class, 'addEmployees']);
        Route::delete('/departments/{id}/employees/{employeeId}', [DepartmentController::class, 'removeEmployee']);
        Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

        // Employees (stored in users with role=employee)
        Route::get('/employees/search', [EmployeeManagementController::class, 'search']);
        Route::get('/employees', [EmployeeManagementController::class, 'index']);
        Route::post('/employees', [EmployeeManagementController::class, 'store']);
        Route::get('/employees/{id}', [EmployeeManagementController::class, 'show']);
        Route::match(['put', 'patch'], '/employees/{id}', [EmployeeManagementController::class, 'update']);
        Route::post('/employees/{id}', [EmployeeManagementController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeManagementController::class, 'destroy']);

        // Permission APIs (Requirement 2 & Screenshot 1)
        Route::get('/permissions/total', [PermissionController::class, 'total']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{role_id}', [PermissionController::class, 'index'])->whereNumber('role_id');
        Route::get('/roles/{id}/permissions', [PermissionController::class, 'index']);

        // Role Permission Assignment APIs (Requirement 3 & Screenshot 1)
        Route::post('/roles/assign-permissions', [RoleController::class, 'assignPermissions']);
        Route::post('/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);

        // Role CRUD & Listing APIs (Requirements 4 & 5, Screenshots 2 & 3)
        Route::get('/roles/search', [RoleController::class, 'search']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::post('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

        // Requirement 5: Shift Management Overview & Statistics (Screenshot 4)
        Route::get('/shifts/overview', [ShiftManagementController::class, 'overview']);
        Route::get('/shift-management/overview', [ShiftManagementController::class, 'overview']);

        // Requirement 2: Shift CRUD APIs (Screenshot 1)
        Route::get('/shifts', [ShiftManagementController::class, 'index']);
        Route::post('/shifts', [ShiftManagementController::class, 'store']);
        Route::get('/shifts/{id}', [ShiftManagementController::class, 'show']);
        Route::put('/shifts/{id}', [ShiftManagementController::class, 'update']);
        Route::post('/shifts/{id}', [ShiftManagementController::class, 'update']);
        Route::delete('/shifts/{id}', [ShiftManagementController::class, 'destroy']);

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

        // Leave request management, calendar, reports, and leave types
        Route::get('/leave-requests/reports', [LeaveManagementController::class, 'reports']);
        Route::match(['put', 'patch'], '/leave-requests/{id}', [LeaveManagementController::class, 'update']);
        Route::delete('/leave-requests/{id}', [LeaveManagementController::class, 'destroy']);

        Route::post('/leave-types', [LeaveManagementController::class, 'storeLeaveType']);
        Route::get('/leave-types/{id}', [LeaveManagementController::class, 'showLeaveType']);
        Route::match(['put', 'patch'], '/leave-types/{id}', [LeaveManagementController::class, 'updateLeaveType']);
        Route::delete('/leave-types/{id}', [LeaveManagementController::class, 'destroyLeaveType']);
        Route::post('/holidays', [LeaveManagementController::class, 'storeHoliday']);

    });
});
