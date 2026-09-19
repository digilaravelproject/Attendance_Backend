<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@empmanagement.com'],
            [
                'name' => 'Administrator',
                'owner_name' => 'Administrator',
                'company_name' => 'Employee Management',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'Active',
            ]
        );
    }
}
