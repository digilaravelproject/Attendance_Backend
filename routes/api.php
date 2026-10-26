<?php

use App\Http\Controllers\Api\AdminAttendanceController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AssignedShiftController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DesignationManagementController;
use App\Http\Controllers\Api\EmployeeAttendanceController;
use App\Http\Controllers\Api\EmployeeLeadController;
use App\Http\Controllers\Api\EmployeeManagementController;
use App\Http\Controllers\Api\FacebookAuthController;
use App\Http\Controllers\Api\JustdialLeadController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadIntegrationStatusController;
use App\Http\Controllers\Api\LeadSourceController;
use App\Http\Controllers\Api\LeadStatusController;
use App\Http\Controllers\Api\LeaveManagementController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectFromLeadController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\ShiftManagementController;
use App\Http\Controllers\Api\ShiftRotationController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WorkingDayController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin / Manager API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('facebook')->middleware(StartSession::class)->group(function () {
    Route::post('/authorization-url', [FacebookAuthController::class, 'generateAuthorizationUrl']);
    Route::match(['get', 'post'], '/callback', [FacebookAuthController::class, 'callback']);
});
Route::post('/justdial_leads', [JustdialLeadController::class, 'store']);
Route::prefix('admin')->group(function () {
    // Public routes
    Route::post('/signup', [AdminAuthController::class, 'signup']);
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password', [AdminAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AdminAuthController::class, 'resetPassword']);

    // Public lookup APIs used by role/employee selectors.
    Route::get('/employees/all-users', [EmployeeManagementController::class, 'allUsers']);
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/salaries/{salary}/payslip', [SalaryController::class, 'payslip'])
        ->whereNumber('salary')->name('salaries.payslip');

    /*
    | Task APIs (No Middleware - Accessible to Admin/Manager/Employee)
    */
    Route::get('/tasks/employees', [TaskController::class, 'getEmployees']);
    Route::get('/tasks/total', [TaskController::class, 'total']);
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/tasks/{id}', [TaskController::class, 'update'])->whereNumber('id');
    Route::post('/tasks/{id}', [TaskController::class, 'update'])->whereNumber('id');
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->whereNumber('id');

    Route::post('/tasks/{id}/timer', [TaskController::class, 'timerAction'])->whereNumber('id');
    Route::post('/tasks/{id}/start', [TaskController::class, 'startTimer'])->whereNumber('id');
    Route::post('/tasks/{id}/pause', [TaskController::class, 'pauseTimer'])->whereNumber('id');
    Route::post('/tasks/{id}/stop', [TaskController::class, 'stopTimer'])->whereNumber('id');

    Route::match(['post', 'patch', 'put'], '/tasks/{id}/status', [TaskController::class, 'updateStatus'])->whereNumber('id');
    Route::post('/tasks/{id}/submit-for-testing', [TaskController::class, 'submitForTesting'])->whereNumber('id');
    Route::post('/tasks/{id}/handover', [TaskController::class, 'handover'])->whereNumber('id');

    Route::post('/tasks/{id}/subtasks', [TaskController::class, 'addSubtask'])->whereNumber('id');
    Route::match(['post', 'patch', 'put'], '/tasks/{id}/subtasks/{subtaskId}/toggle', [TaskController::class, 'toggleSubtask'])->whereNumber(['id', 'subtaskId']);
    Route::delete('/tasks/{id}/subtasks/{subtaskId}', [TaskController::class, 'deleteSubtask'])->whereNumber(['id', 'subtaskId']);

    Route::get('/tasks/{id}/comments', [TaskController::class, 'getComments'])->whereNumber('id');
    Route::post('/tasks/{id}/comments', [TaskController::class, 'addComment'])->whereNumber('id');

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
        Route::get('/birthdays/upcoming', [EmployeeAttendanceController::class, 'upcomingBirthdays']);
        Route::get('/salary-history', [SalaryController::class, 'myHistory']);
        Route::get('/salaries/my-history', [SalaryController::class, 'myHistory']);

        // Authenticated API without administrator/employee role restrictions.
        Route::get('/leads/sales-employees', [LeadController::class, 'salesEmployees']);
        Route::get('/leads/sales-employees/{employee}', [LeadController::class, 'salesEmployeeDetails'])->whereNumber('employee');
        Route::get('/lead-sources', [LeadSourceController::class, 'index']);
        Route::post('/lead-sources', [LeadSourceController::class, 'store']);
        Route::get('/lead-sources/{leadSource}', [LeadSourceController::class, 'show'])->whereNumber('leadSource');
        Route::match(['put', 'patch'], '/lead-sources/{leadSource}', [LeadSourceController::class, 'update'])->whereNumber('leadSource');
        Route::delete('/lead-sources/{leadSource}', [LeadSourceController::class, 'destroy'])->whereNumber('leadSource');
        Route::get('/lead-statuses', [LeadStatusController::class, 'index']);

        // Shared CRM/project lookups and actions for any authenticated active user.
        Route::get('/working-days', WorkingDayController::class);
        Route::get('/managers', ManagerController::class);
        Route::post('/leads/{lead}/projects', ProjectFromLeadController::class)->whereNumber('lead');

        Route::get('/clients/search', [ClientController::class, 'search']);
        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{client}', [ClientController::class, 'show'])->whereNumber('client');
        Route::match(['put', 'patch'], '/clients/{client}', [ClientController::class, 'update'])->whereNumber('client');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->whereNumber('client');
        Route::post('/clients/{client}/projects', [ClientController::class, 'assignProject'])->whereNumber('client');
        Route::get('/clients/{client}/projects', [ClientController::class, 'projects'])->whereNumber('client');
        Route::get('/clients/{client}/communications', [ClientController::class, 'communications'])->whereNumber('client');
        Route::post('/clients/{client}/communications', [ClientController::class, 'storeCommunication'])->whereNumber('client');
        Route::get('/clients/{client}/communications/{communication}', [ClientController::class, 'showCommunication'])->whereNumber(['client', 'communication']);
        Route::match(['put', 'patch'], '/clients/{client}/communications/{communication}', [ClientController::class, 'updateCommunication'])->whereNumber(['client', 'communication']);
        Route::delete('/clients/{client}/communications/{communication}', [ClientController::class, 'destroyCommunication'])->whereNumber(['client', 'communication']);

        // Dynamic performance APIs. Visibility is enforced in the controller:
        // admin = all employees, manager = self/direct reports, employee = self.
        Route::get('/performance/employees', [PerformanceController::class, 'index']);
        Route::get('/performance/employees/{employee}', [PerformanceController::class, 'show'])->whereNumber('employee');
        Route::get('/performance/employees/{employee}/attendance', [PerformanceController::class, 'attendance'])->whereNumber('employee');
        Route::get('/performance/employees/{employee}/leave', [PerformanceController::class, 'leave'])->whereNumber('employee');
        Route::get('/performance/employees/{employee}/tasks', [PerformanceController::class, 'tasks'])->whereNumber('employee');
        Route::get('/performance/employees/{employee}/task-completion', [PerformanceController::class, 'tasks'])->whereNumber('employee');
        Route::get('/performance/employees/{employee}/timely-submissions', [PerformanceController::class, 'tasks'])->whereNumber('employee');
        Route::get('/performance/employees/{employee}/quality', [PerformanceController::class, 'quality'])->whereNumber('employee');
        Route::post('/performance/employees/{employee}/quality-reviews', [PerformanceController::class, 'storeQualityReview'])->whereNumber('employee');
        Route::get('/performance/employees/{employee}/messages', [PerformanceController::class, 'messages'])->whereNumber('employee');
        Route::post('/performance/employees/{employee}/messages', [PerformanceController::class, 'sendMessage'])->whereNumber('employee');

        // Project management for administrators/managers and assigned-project views for employees.
        Route::get('/projects/assigned', [ProjectController::class, 'assigned']);
        Route::get('/projects/assigned/{id}', [ProjectController::class, 'assignedShow'])->whereNumber('id');
        Route::get('/projects/total', [ProjectController::class, 'total']);
        Route::get('/projects/search', [ProjectController::class, 'search']);
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{id}', [ProjectController::class, 'show'])->whereNumber('id');
        Route::match(['put', 'patch'], '/projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
        Route::post('/projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
        Route::delete('/projects/{id}/employees/{employeeId}', [ProjectController::class, 'removeEmployee'])->whereNumber(['id', 'employeeId']);
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->whereNumber('id');

    });

    // Employee panel routes use the same /api/admin authorization endpoints and token.
    Route::middleware(['auth:sanctum', 'employee'])->group(function () {
        Route::post('/attendance/check-in', [EmployeeAttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [EmployeeAttendanceController::class, 'checkOut']);
        Route::post('/attendance/mark', [EmployeeAttendanceController::class, 'checkIn']);
        Route::post('/attendance/mark-logout', [EmployeeAttendanceController::class, 'checkOut']);
        Route::get('/attendance/history', [EmployeeAttendanceController::class, 'history']);
    });

    // Protected routes (Sanctum Auth Token Required)
    Route::middleware(['auth:sanctum', 'admin', 'track.admin.action'])->group(function () {
        // CRM lead hub.
        Route::get('/lead-statuses/total', [LeadStatusController::class, 'total']);
        Route::post('/lead-statuses', [LeadStatusController::class, 'store']);
        Route::get('/lead-statuses/{leadStatus}', [LeadStatusController::class, 'show'])->whereNumber('leadStatus');
        Route::match(['put', 'patch'], '/lead-statuses/{leadStatus}', [LeadStatusController::class, 'update'])->whereNumber('leadStatus');
        Route::delete('/lead-statuses/{leadStatus}', [LeadStatusController::class, 'destroy'])->whereNumber('leadStatus');
        Route::get('/lead-integrations/status', [LeadIntegrationStatusController::class, 'show']);

        Route::get('/leads/search', [LeadController::class, 'search']);
        Route::get('/leads', [LeadController::class, 'index']);
        Route::post('/leads', [LeadController::class, 'store']);
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead');
        Route::post('/leads/{lead}/assign', [LeadController::class, 'assign'])->whereNumber('lead');

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
        Route::get('/employees/leaves', [LeaveManagementController::class, 'allEmployeeLeaves']);
        Route::get('/employees/leave-reports', [LeaveManagementController::class, 'allEmployeeReports']);
        Route::get('/employees', [EmployeeManagementController::class, 'index']);
        Route::post('/employees', [EmployeeManagementController::class, 'store']);
        Route::get('/employees/{id}', [EmployeeManagementController::class, 'show']);
        Route::match(['put', 'patch'], '/employees/{id}', [EmployeeManagementController::class, 'update']);
        Route::post('/employees/{id}', [EmployeeManagementController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeManagementController::class, 'destroy']);

        // Attendance and payroll management.
        Route::get('/attendance', [AdminAttendanceController::class, 'index']);
        Route::get('/salaries', [SalaryController::class, 'index']);
        Route::get('/salaries/employee/{employeeId}', [SalaryController::class, 'showEmployee']);
        Route::get('/salaries/employee/{employeeId}/breakdown', [SalaryController::class, 'breakdown']);
        Route::post('/salaries', [SalaryController::class, 'store']);

        // Permission APIs (Requirement 2 & Screenshot 1)
        Route::get('/permissions/total', [PermissionController::class, 'total']);
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
        Route::get('/holidays/{id}', [LeaveManagementController::class, 'showHoliday'])->whereNumber('id');
        Route::match(['put', 'patch'], '/holidays/{id}', [LeaveManagementController::class, 'updateHoliday'])->whereNumber('id');
        Route::delete('/holidays/{id}', [LeaveManagementController::class, 'destroyHoliday'])->whereNumber('id');

    });
});

/*
|--------------------------------------------------------------------------
| Employee Sales CRM API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('employee')->middleware('auth:sanctum')->group(function () {
    Route::get('/leads/search', [EmployeeLeadController::class, 'search']);
    Route::get('/leads/sales-employees', [EmployeeLeadController::class, 'salesEmployees']);
    Route::get('/leads', [EmployeeLeadController::class, 'index']);
    Route::post('/leads', [EmployeeLeadController::class, 'store']);
    Route::get('/leads/{lead}', [EmployeeLeadController::class, 'show'])->whereNumber('lead');
    Route::post('/leads/{lead}/assign', [EmployeeLeadController::class, 'assign'])->whereNumber('lead');
    Route::match(['put', 'patch', 'post'], '/leads/{lead}/status', [EmployeeLeadController::class, 'updateStatus'])->whereNumber('lead');
    Route::post('/leads/{lead}/reminder', [EmployeeLeadController::class, 'setReminder'])->whereNumber('lead');
    Route::post('/leads/{lead}/reminders', [EmployeeLeadController::class, 'setReminder'])->whereNumber('lead');
});

/*
|--------------------------------------------------------------------------
| Top-level Task API Aliases (/api/tasks) - No Middleware
|--------------------------------------------------------------------------
*/
Route::prefix('tasks')->group(function () {
    Route::get('/employees', [TaskController::class, 'getEmployees']);
    Route::get('/total', [TaskController::class, 'total']);
    Route::get('/', [TaskController::class, 'index']);
    Route::post('/', [TaskController::class, 'store']);
    Route::get('/{id}', [TaskController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [TaskController::class, 'update'])->whereNumber('id');
    Route::post('/{id}', [TaskController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [TaskController::class, 'destroy'])->whereNumber('id');

    Route::post('/{id}/timer', [TaskController::class, 'timerAction'])->whereNumber('id');
    Route::post('/{id}/start', [TaskController::class, 'startTimer'])->whereNumber('id');
    Route::post('/{id}/pause', [TaskController::class, 'pauseTimer'])->whereNumber('id');
    Route::post('/{id}/stop', [TaskController::class, 'stopTimer'])->whereNumber('id');

    Route::match(['post', 'patch', 'put'], '/{id}/status', [TaskController::class, 'updateStatus'])->whereNumber('id');
    Route::post('/{id}/submit-for-testing', [TaskController::class, 'submitForTesting'])->whereNumber('id');
    Route::post('/{id}/handover', [TaskController::class, 'handover'])->whereNumber('id');

    Route::post('/{id}/subtasks', [TaskController::class, 'addSubtask'])->whereNumber('id');
    Route::match(['post', 'patch', 'put'], '/{id}/subtasks/{subtaskId}/toggle', [TaskController::class, 'toggleSubtask'])->whereNumber(['id', 'subtaskId']);
    Route::delete('/{id}/subtasks/{subtaskId}', [TaskController::class, 'deleteSubtask'])->whereNumber(['id', 'subtaskId']);

    Route::get('/{id}/comments', [TaskController::class, 'getComments'])->whereNumber('id');
    Route::post('/{id}/comments', [TaskController::class, 'addComment'])->whereNumber('id');
});
