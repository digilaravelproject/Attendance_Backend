<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\PasswordOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_and_login_use_the_admins_table(): void
    {
        $payload = [
            'company_name' => 'Acme Corporation',
            'owner_name' => 'John Doe',
            'mobile_number' => '9876543210',
            'email' => 'john@acme.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $this->postJson('/api/admin/signup', $payload)
            ->assertCreated()
            ->assertJsonPath('data.email', 'john@acme.com')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('admins', ['email' => 'john@acme.com']);
        $this->assertDatabaseMissing('users', ['email' => 'john@acme.com']);

        $this->postJson('/api/admin/signup', $payload)->assertUnprocessable();
        $this->postJson('/api/admin/login', [
            'email' => 'john@acme.com',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_profile_can_upload_list_and_delete_documents(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $upload = $this->actingAs($admin)->post('/api/admin/update-profile', [
            'company_name' => 'Updated Company',
            'documents' => [
                UploadedFile::fake()->create('contract.pdf', 25, 'application/pdf'),
                UploadedFile::fake()->image('identity.jpg'),
            ],
        ]);

        $upload->assertOk()
            ->assertJsonPath('data.company_name', 'Updated Company')
            ->assertJsonCount(2, 'data.documents');

        $documentId = $upload->json('data.documents.0.id');
        $this->actingAs($admin)->getJson('/api/admin/profile')
            ->assertOk()
            ->assertJsonCount(2, 'data.documents');

        $this->actingAs($admin)->deleteJson("/api/admin/documents/{$documentId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.documents');
    }

    public function test_forgot_and_reset_password_for_admin(): void
    {
        $admin = $this->admin();

        $this->postJson('/api/admin/forgot-password', ['email' => $admin->email])->assertOk();
        $otp = PasswordOtp::where('email', $admin->email)->firstOrFail();

        $this->postJson('/api/admin/reset-password', [
            'email' => $admin->email,
            'otp' => $otp->otp,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/admin/profile')->assertUnauthorized();
    }

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Test Admin',
            'company_name' => 'Test Company',
            'owner_name' => 'Test Admin',
            'mobile_number' => '9876543210',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);
    }
}
