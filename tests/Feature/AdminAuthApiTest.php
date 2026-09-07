<?php

namespace Tests\Feature;

use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_creates_account_and_returns_token(): void
    {
        $response = $this->postJson('/api/admin/signup', [
            'company_name' => 'Acme Corporation',
            'owner_name' => 'John Doe',
            'mobile_number' => '9876543210',
            'email' => 'john@acme.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['id', 'company_name', 'owner_name', 'mobile_number', 'email'],
                'access_token',
                'token_type',
            ])
            ->assertJson([
                'status' => true,
                'data' => [
                    'company_name' => 'Acme Corporation',
                    'owner_name' => 'John Doe',
                    'mobile_number' => '9876543210',
                    'email' => 'john@acme.com',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@acme.com',
            'company_name' => 'Acme Corporation',
        ]);
    }

    public function test_signup_validates_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing Owner',
            'company_name' => 'Existing Corp',
            'owner_name' => 'Existing Owner',
            'mobile_number' => '9876543210',
            'email' => 'existing@acme.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/admin/signup', [
            'company_name' => 'New Corp',
            'owner_name' => 'New Owner',
            'mobile_number' => '9876543211',
            'email' => 'existing@acme.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation error',
            ])
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_token_with_valid_credentials(): void
    {
        User::create([
            'name' => 'John Doe',
            'company_name' => 'Acme Corp',
            'owner_name' => 'John Doe',
            'mobile_number' => '9876543210',
            'email' => 'john@acme.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'john@acme.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ])
            ->assertJsonStructure(['access_token']);
    }

    public function test_forgot_password_generates_and_stores_6_digit_otp(): void
    {
        User::create([
            'name' => 'John Doe',
            'company_name' => 'Acme Corp',
            'owner_name' => 'John Doe',
            'mobile_number' => '9876543210',
            'email' => 'john@acme.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/admin/forgot-password', [
            'email' => 'john@acme.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'A 6-digit verification code has been sent to your email address.',
            ]);

        $this->assertDatabaseHas('password_otps', [
            'email' => 'john@acme.com',
        ]);

        $otp = PasswordOtp::where('email', 'john@acme.com')->first();
        $this->assertEquals(6, strlen($otp->otp));
    }

    public function test_reset_password_with_valid_otp_updates_password(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'company_name' => 'Acme Corp',
            'owner_name' => 'John Doe',
            'mobile_number' => '9876543210',
            'email' => 'john@acme.com',
            'password' => bcrypt('oldpassword'),
        ]);

        PasswordOtp::create([
            'email' => 'john@acme.com',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/admin/reset-password', [
            'email' => 'john@acme.com',
            'otp' => '123456',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Password updated successfully. Please login with your new password.',
            ]);

        // Attempt login with new password
        $loginResponse = $this->postJson('/api/admin/login', [
            'email' => 'john@acme.com',
            'password' => 'newpassword123',
        ]);

        $loginResponse->assertStatus(200);
    }

    public function test_unauthenticated_request_to_profile_returns_401(): void
    {
        $response = $this->getJson('/api/admin/profile');

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'Unauthenticated. Access token is missing or invalid.',
            ]);
    }

    public function test_authenticated_profile_get_and_update(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'company_name' => 'Tech Solutions',
            'owner_name' => 'Jane Doe',
            'mobile_number' => '9123456789',
            'email' => 'jane@tech.com',
            'password' => bcrypt('secret123'),
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        // Get Profile
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/profile');

        $getResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'email' => 'jane@tech.com',
                    'company_name' => 'Tech Solutions',
                ]
            ]);

        // Update Profile
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/admin/profile', [
                'company_name' => 'Tech Solutions Global',
                'owner_name' => 'Jane S. Doe',
                'mobile_number' => '9123456780',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'company_name' => 'Tech Solutions Global',
                    'owner_name' => 'Jane S. Doe',
                    'mobile_number' => '9123456780',
                ]
            ]);
    }
}
