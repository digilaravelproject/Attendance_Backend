<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AdminAuthController;
use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedAdmin
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();
        $usesAdminAccount = str_starts_with(
            (string) $request->route()?->getActionName(),
            AdminAuthController::class
        );
        $isAdmin = $user instanceof Admin
            || ($user instanceof User
                && strtolower((string) $user->role) === 'admin'
                && ! $usesAdminAccount);

        if (! $isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden. An authenticated administrator is required.',
            ], 403);
        }

        return $next($request);
    }
}
