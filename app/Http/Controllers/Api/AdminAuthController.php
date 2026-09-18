<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendOtpMail;
use App\Models\Admin;
use App\Models\AdminDocument;
use App\Models\PasswordOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AdminAuthController extends Controller
{
    /**
     * Admin/Manager Signup
     */
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'mobile_number' => 'required|string|regex:/^[0-9]{10}$/',
            'email' => 'required|string|email|max:255|unique:admins,email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'mobile_number.regex' => 'The mobile number must be exactly 10 digits.',
            'email.unique' => 'This email address is already registered. Please login instead.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Admin::create([
            'name' => $request->owner_name,
            'company_name' => $request->company_name,
            'owner_name' => $request->owner_name,
            'mobile_number' => $request->mobile_number,
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('admin_auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Account created successfully.',
            'data' => $this->profileData($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Admin/Manager Login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Admin::where('email', strtolower(trim($request->email)))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email address or password.',
            ], 401);
        }

        $token = $user->createToken('admin_auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => $this->profileData($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * Send 6-digit OTP to Email for Forgot Password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:admins,email',
        ], [
            'email.exists' => 'We could not find an account registered with this email address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $user = Admin::where('email', $email)->first();

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Clear existing OTPs for this email
        PasswordOtp::where('email', $email)->delete();

        // Save new OTP valid for 15 minutes
        PasswordOtp::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(15),
        ]);

        try {
            Mail::to($email)->send(new SendOtpMail($otp, $user->owner_name ?? $user->name));
        } catch (Throwable $e) {
            // Log mail failure but allow for dev testing if mail transport is unconfigured
            logger()->error('Failed to send OTP email: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'A 6-digit verification code has been sent to your email address.',
            'otp_debug' => config('app.debug') ? $otp : null, // Helper for local dev if mailer is log
        ], 200);
    }

    /**
     * Update/Reset Password using Verification Code
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:admins,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'otp.size' => 'The verification code must be exactly 6 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $otpRecord = PasswordOtp::where('email', $email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid verification code.',
            ], 422);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->delete();
            return response()->json([
                'status' => false,
                'message' => 'Verification code has expired. Please request a new code.',
            ], 422);
        }

        // Update User Password
        $user = Admin::where('email', $email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete used OTP
        $otpRecord->delete();

        // Revoke all existing tokens for safety
        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully. Please login with your new password.',
        ], 200);
    }

    /**
     * Get Logged-in Profile Details
     */
    public function getProfile(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Profile details retrieved successfully.',
            'data' => $this->profileData($request->user()),
        ], 200);
    }

    /**
     * Update Profile Details
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|nullable|string|max:255',
            'owner_name' => 'sometimes|nullable|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'mobile_number' => 'sometimes|nullable|string',
            'phone' => 'sometimes|nullable|string',
            'email' => 'sometimes|required|string|email|max:255|unique:admins,email,' . $user->id,
            'department' => 'sometimes|nullable|string|max:255',
            'designation' => 'sometimes|nullable|string|max:255',
            'employee_id' => 'sometimes|nullable|string|max:255',
            'date_of_joining' => 'sometimes|nullable|date',
            'address' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string|max:50',
            'avatar' => 'sometimes|nullable',
            'documents' => 'sometimes|nullable|array|max:10',
            'documents.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ], [
            'email.unique' => 'This email address is already in use by another account.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
            $user->owner_name = $request->name;
        } elseif ($request->has('owner_name')) {
            $user->owner_name = $request->owner_name;
            $user->name = $request->owner_name;
        }

        if ($request->has('company_name')) {
            $user->company_name = $request->company_name;
        }

        if ($request->has('mobile_number')) {
            $user->mobile_number = $request->mobile_number;
            if (!$request->has('phone')) {
                $user->phone = $request->mobile_number;
            }
        }

        if ($request->has('phone')) {
            $user->phone = $request->phone;
            if (!$request->has('mobile_number')) {
                $user->mobile_number = $request->phone;
            }
        }

        if ($request->has('email')) {
            $user->email = strtolower(trim($request->email));
        }

        if ($request->has('department')) {
            $user->department = $request->department;
        }

        if ($request->has('designation')) {
            $user->designation = $request->designation;
        }

        if ($request->has('employee_id')) {
            $user->employee_id = $request->employee_id;
        }

        if ($request->has('date_of_joining')) {
            $user->date_of_joining = $request->date_of_joining;
        }

        if ($request->has('address')) {
            $user->address = $request->address;
        }

        if ($request->has('status')) {
            $user->status = $request->status;
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = asset('storage/' . $path);
        } elseif ($request->has('avatar') && is_string($request->avatar)) {
            $user->avatar = $request->avatar;
        }

        $user->save();

        foreach ($request->file('documents', []) as $document) {
            $path = $document->store('admin-documents/' . $user->id, 'public');
            $user->documents()->create([
                'original_name' => $document->getClientOriginalName(),
                'file_name' => Str::afterLast($path, '/'),
                'file_path' => $path,
                'mime_type' => $document->getMimeType(),
                'size' => $document->getSize(),
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully.',
            'data' => $this->profileData($user->fresh()),
        ], 200);
    }

    public function deleteDocument(Request $request, string $document): JsonResponse
    {
        $adminDocument = AdminDocument::where('admin_id', $request->user()->id)->find($document);

        if (! $adminDocument) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found.',
            ], 404);
        }

        Storage::disk('public')->delete($adminDocument->file_path);
        $adminDocument->delete();

        return response()->json([
            'status' => true,
            'message' => 'Document deleted successfully.',
            'data' => $this->profileData($request->user()->fresh()),
        ]);
    }

    /**
     * Update Password for Logged-in User
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Accept confirm_password or new_password_confirmation
        $confirmPassword = $request->input('confirm_password', $request->input('new_password_confirmation'));
        $request->merge(['new_password_confirmation' => $confirmPassword]);

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[!@#$%&*]/',
                'confirmed'
            ],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'new_password.required' => 'Please enter a new password.',
            'new_password.min' => 'Password must contain at least 8 characters.',
            'new_password.regex' => 'Password must contain at least one uppercase letter and one special character (!@#$%&*).',
            'new_password.confirmed' => 'New password and confirm password do not match.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'The current password provided is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully.',
        ], 200);
    }

    /**
     * Logout Admin/Manager
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    private function profileData(Admin $admin): array
    {
        $data = $admin->toArray();
        $data['documents'] = $admin->documents()
            ->latest()
            ->get()
            ->map(fn (AdminDocument $document) => [
                'id' => $document->id,
                'original_name' => $document->original_name,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'url' => asset('storage/' . $document->file_path),
                'created_at' => $document->created_at,
            ])
            ->values()
            ->all();

        return $data;
    }
}
