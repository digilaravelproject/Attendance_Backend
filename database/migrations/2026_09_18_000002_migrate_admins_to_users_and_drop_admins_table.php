<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('admin_id')->constrained('users')->cascadeOnDelete();
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
        });
        Schema::table('leave_request_actions', function (Blueprint $table) {
            $table->foreignId('actor_user_id')->nullable()->after('admin_id')->constrained('users')->nullOnDelete();
        });

        $userIdsByAdminId = [];
        foreach (DB::table('admins')->orderBy('id')->get() as $admin) {
            $existingUser = DB::table('users')->where('email', $admin->email)->first();
            $values = [
                'name' => $admin->name,
                'company_name' => $admin->company_name,
                'owner_name' => $admin->owner_name,
                'mobile_number' => $admin->mobile_number,
                'phone' => $admin->phone,
                'password' => $admin->password,
                'role' => 'admin',
                'department' => $admin->department,
                'designation' => $admin->designation,
                'employee_id' => $admin->employee_id,
                'date_of_joining' => $admin->date_of_joining,
                'address' => $admin->address,
                'avatar' => $admin->avatar,
                'status' => $admin->status,
                'email_verified_at' => $admin->email_verified_at,
                'remember_token' => $admin->remember_token,
                'updated_at' => $admin->updated_at ?? now(),
            ];

            if ($existingUser) {
                DB::table('users')->where('id', $existingUser->id)->update($values);
                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    ...$values,
                    'email' => $admin->email,
                    'created_at' => $admin->created_at ?? now(),
                ]);
            }

            $userIdsByAdminId[$admin->id] = $userId;
        }

        foreach ($userIdsByAdminId as $adminId => $userId) {
            DB::table('admin_documents')->where('admin_id', $adminId)->update(['user_id' => $userId]);
            DB::table('leave_requests')->where('reviewed_by', $adminId)->update(['reviewed_by_user_id' => $userId]);
            DB::table('leave_request_actions')->where('admin_id', $adminId)->update(['actor_user_id' => $userId]);
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\Admin')
                ->where('tokenable_id', $adminId)
                ->update([
                    'tokenable_type' => 'App\\Models\\User',
                    'tokenable_id' => $userId,
                ]);
        }

        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
        });
        Schema::table('leave_request_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });

        Schema::dropIfExists('admins');
    }

    public function down(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('employee_id')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->text('address')->nullable();
            $table->string('avatar')->nullable();
            $table->string('status')->default('Active');
            $table->rememberToken();
            $table->timestamps();
        });

        $adminIdsByUserId = [];
        foreach (DB::table('users')->where('role', 'admin')->orderBy('id')->get() as $user) {
            $adminId = DB::table('admins')->insertGetId([
                'name' => $user->name,
                'company_name' => $user->company_name,
                'owner_name' => $user->owner_name,
                'mobile_number' => $user->mobile_number,
                'phone' => $user->phone,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'department' => $user->department,
                'designation' => $user->designation,
                'employee_id' => $user->employee_id,
                'date_of_joining' => $user->date_of_joining,
                'address' => $user->address,
                'avatar' => $user->avatar,
                'status' => $user->status,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
            $adminIdsByUserId[$user->id] = $adminId;
        }

        Schema::table('admin_documents', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('user_id')->constrained('admins')->cascadeOnDelete();
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_by_user_id')->constrained('admins')->nullOnDelete();
        });
        Schema::table('leave_request_actions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('actor_user_id')->constrained('admins')->nullOnDelete();
        });

        foreach ($adminIdsByUserId as $userId => $adminId) {
            DB::table('admin_documents')->where('user_id', $userId)->update(['admin_id' => $adminId]);
            DB::table('leave_requests')->where('reviewed_by_user_id', $userId)->update(['reviewed_by' => $adminId]);
            DB::table('leave_request_actions')->where('actor_user_id', $userId)->update(['admin_id' => $adminId]);
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->where('tokenable_id', $userId)
                ->update([
                    'tokenable_type' => 'App\\Models\\Admin',
                    'tokenable_id' => $adminId,
                ]);
        }

        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
        });
        Schema::table('leave_request_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_user_id');
        });
    }
};
