<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDocumentUploadAliasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_returns_documents_uploaded_with_legacy_field_names(): void
    {
        Storage::fake('public');
        $admin = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post('/api/admin/update-profile', [
            'documnts' => [
                UploadedFile::fake()->create('invitation.pdf', 25, 'application/pdf'),
            ],
            'document' => [
                UploadedFile::fake()->image('identity.jpg'),
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.documents')
            ->assertJsonFragment(['original_name' => 'invitation.pdf'])
            ->assertJsonFragment(['original_name' => 'identity.jpg']);

        $this->assertDatabaseCount('admin_documents', 2);
    }
}
