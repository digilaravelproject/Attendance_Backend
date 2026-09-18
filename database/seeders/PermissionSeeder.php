<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Profile & Documents' => [
                'View Profile Details', 'Edit Profile Info', 'Change Avatar', 'View Address',
                'View Bank Details', 'Edit Bank Details', 'View Documents', 'Upload Documents',
                'Zoom & Preview Document', 'Delete Documents',
            ],
            'Attendance & Regularization' => [
                'View Attendance Screen', 'Check-In / Check-Out', 'View Attendance History',
                'Request Regularization', 'Approve Regularization', 'View Team Attendance',
                'Export Attendance',
            ],
            'Leave Management' => [
                'View Leave Dashboard', 'Apply for Leave', 'Cancel Leave', 'View Leave Balance',
                'Approve / Reject Leave', 'All Employees Requests', 'View Leave Policy',
            ],
            'Employee Management' => [
                'View Employee Directory', 'Add New Employee', 'Edit Employee Details',
                'Delete Employee', 'View Salary Structure', 'Edit Salary Structure',
                'View Uploaded Documents',
            ],
            'Tasks & Projects' => [
                'View Tasks', 'Create Task', 'Edit Task', 'Delete Task', 'Assign Task',
                'Daily Task Update', 'View Projects', 'Create Project', 'Edit Project', 'Delete Project',
            ],
            'Departments & Designations' => [
                'View Departments', 'Add Department', 'Edit Department', 'Delete Department',
                'View Designations', 'Add Designation', 'Edit Designation', 'Delete Designation',
            ],
            'Payroll & Salary' => [
                'View My Salary', 'Manage Company Payroll', 'Process Monthly Payroll', 'Download Payslip',
            ],
            'Assets Management' => [
                'View Assets', 'Add New Asset', 'Edit Asset Details', 'Assign Asset to Staff',
                'Accept Asset Return',
            ],
            'Clients & Leads (CRM)' => [
                'View Leads', 'Add Lead', 'Edit Lead', 'Delete Lead', 'Assign Sales Team',
                'Update Lead Status', 'View Clients', 'Create Client', 'Edit Client',
            ],
            'Meetings & Follow-ups' => [
                'View Meetings', 'Schedule Meeting', 'Edit / Reschedule Meeting', 'Add Meeting Notes',
            ],
            'Documents & Folders' => [
                'Browse Document Folders', 'Upload Documents', 'Delete Documents', 'Manage Access Control',
            ],
            'Company Profile & Policies' => [
                'View Company Profile', 'Edit Company Profile', 'Read Compliance Policies',
                'Upload Compliance Policies',
            ],
            'Roles & Permissions (RBAC)' => [
                'View Roles List', 'Create New Role', 'Edit Role & Permissions', 'Delete Role',
            ],
        ];

        DB::transaction(function () use ($groups) {
            $permissionIds = [];

            foreach ($groups as $module => $permissionNames) {
                $moduleSlug = Str::slug($module, '_');

                foreach ($permissionNames as $permissionName) {
                    $action = Str::slug($permissionName, '_');
                    $permission = Permission::updateOrCreate(
                        ['module_slug' => $moduleSlug, 'action' => $action],
                        [
                            'module' => $module,
                            'name' => $permissionName,
                            'description' => $this->descriptionFor($permissionName),
                        ]
                    );
                    $permissionIds[] = $permission->id;
                }
            }

            Permission::whereNotIn('id', $permissionIds)->delete();

            $adminRole = Role::updateOrCreate(
                ['name' => 'Admin'],
                [
                    'department' => 'Management',
                    'description' => 'Full system access',
                    'status' => true,
                ]
            );
            $adminRole->permissions()->sync($permissionIds);
        });
    }

    private function descriptionFor(string $permission): string
    {
        return match ($permission) {
            'View My Salary' => 'See personal payslip and salary history',
            'Manage Company Payroll' => 'Access company-wide payroll module',
            'Process Monthly Payroll' => 'Calculate and generate monthly payroll',
            'Download Payslip' => 'Generate and download salary PDF slip',
            default => $permission,
        };
    }
}
