<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'description' => 'Manage dashboard data',
            ],
            [
                'name' => 'Users',
                'slug' => 'users',
                'description' => 'Manage users data',
            ],
            [
                'name' => 'Employees',
                'slug' => 'employees',
                'description' => 'Manage employee data',
            ],
            [
                'name' => 'Attendance',
                'slug' => 'attendance',
                'description' => 'Manage attendance data',
            ],
            [
                'name' => 'Leaves',
                'slug' => 'leaves',
                'description' => 'Manage leaves data',
            ],
            [
                'name' => 'Payroll',
                'slug' => 'payroll',
                'description' => 'Manage payroll data',
            ],
            [
                'name' => 'Reports',
                'slug' => 'reports',
                'description' => 'Manage reports data',
            ],
            [
                'name' => 'Settings',
                'slug' => 'settings',
                'description' => 'Manage settings data',
            ],
        ];

        $actions = ['view', 'add', 'edit', 'delete'];

        $createdPermissions = [];

        foreach ($modules as $mod) {
            foreach ($actions as $act) {
                $perm = Permission::updateOrCreate(
                    [
                        'module_slug' => $mod['slug'],
                        'action' => $act,
                    ],
                    [
                        'module' => $mod['name'],
                        'name' => $act . '_' . $mod['slug'],
                        'description' => $mod['description'],
                    ]
                );
                $createdPermissions[$mod['slug']][$act] = $perm->id;
            }
        }

        // Seed Roles matching Screenshot 3
        $adminRole = Role::updateOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Full system access', 'status' => true]
        );

        $hrRole = Role::updateOrCreate(
            ['name' => 'HR Manager'],
            ['description' => 'Manage HR & Employee', 'status' => true]
        );

        $managerRole = Role::updateOrCreate(
            ['name' => 'Manager'],
            ['description' => 'Manage team & projects', 'status' => true]
        );

        $teamLeadRole = Role::updateOrCreate(
            ['name' => 'Team Lead'],
            ['description' => 'Team supervision', 'status' => true]
        );

        // Admin Permissions matching Screenshot 1 (26 total permissions)
        // Dashboard: view, add, edit, delete (4)
        // Users: view, add, edit, delete (4)
        // Employees: view, add, edit, delete (4)
        // Attendance: view, add, edit, delete (4)
        // Leaves: view, add, edit, delete (4)
        // Payroll: view, add, edit (3)
        // Reports: view (1)
        // Settings: view, add (2)
        $adminPermissionIds = [
            // Dashboard
            $createdPermissions['dashboard']['view'],
            $createdPermissions['dashboard']['add'],
            $createdPermissions['dashboard']['edit'],
            $createdPermissions['dashboard']['delete'],
            // Users
            $createdPermissions['users']['view'],
            $createdPermissions['users']['add'],
            $createdPermissions['users']['edit'],
            $createdPermissions['users']['delete'],
            // Employees
            $createdPermissions['employees']['view'],
            $createdPermissions['employees']['add'],
            $createdPermissions['employees']['edit'],
            $createdPermissions['employees']['delete'],
            // Attendance
            $createdPermissions['attendance']['view'],
            $createdPermissions['attendance']['add'],
            $createdPermissions['attendance']['edit'],
            $createdPermissions['attendance']['delete'],
            // Leaves
            $createdPermissions['leaves']['view'],
            $createdPermissions['leaves']['add'],
            $createdPermissions['leaves']['edit'],
            $createdPermissions['leaves']['delete'],
            // Payroll
            $createdPermissions['payroll']['view'],
            $createdPermissions['payroll']['add'],
            $createdPermissions['payroll']['edit'],
            // Reports
            $createdPermissions['reports']['view'],
            // Settings
            $createdPermissions['settings']['view'],
            $createdPermissions['settings']['add'],
        ];

        $adminRole->permissions()->sync($adminPermissionIds);

        // HR Manager Permissions (18 permissions)
        $hrPermissionIds = array_slice(array_values(Permission::pluck('id')->toArray()), 0, 18);
        $hrRole->permissions()->sync($hrPermissionIds);

        // Manager Permissions (8 permissions)
        $managerPermissionIds = array_slice(array_values(Permission::pluck('id')->toArray()), 0, 8);
        $managerRole->permissions()->sync($managerPermissionIds);

        // Team Lead Permissions (7 permissions)
        $teamLeadPermissionIds = array_slice(array_values(Permission::pluck('id')->toArray()), 0, 7);
        $teamLeadRole->permissions()->sync($teamLeadPermissionIds);

        // Seed assigned Users matching Screenshot 2 (John Doe, Sarah Smith, Michael Brown)
        $user1 = User::updateOrCreate(
            ['email' => 'john.doe@example.com'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('password'),
                'department' => 'Management',
                'designation' => 'Administrator',
                'status' => 'active',
            ]
        );

        $user2 = User::updateOrCreate(
            ['email' => 'sarah.smith@example.com'],
            [
                'name' => 'Sarah Smith',
                'password' => Hash::make('password'),
                'department' => 'HR',
                'designation' => 'HR Executive',
                'status' => 'active',
            ]
        );

        $user3 = User::updateOrCreate(
            ['email' => 'michael.brown@example.com'],
            [
                'name' => 'Michael Brown',
                'password' => Hash::make('password'),
                'department' => 'IT',
                'designation' => 'Lead Developer',
                'status' => 'active',
            ]
        );

        $adminRole->users()->sync([$user1->id, $user2->id, $user3->id]);
        $hrRole->users()->sync([$user2->id]);
        $managerRole->users()->sync([$user3->id]);
        $teamLeadRole->users()->sync([$user1->id, $user3->id]);
    }
}
