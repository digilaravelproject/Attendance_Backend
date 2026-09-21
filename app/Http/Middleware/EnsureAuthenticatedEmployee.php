<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedEmployee
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || strtolower((string) $user->role) !== 'employee') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden. An authenticated employee is required.',
            ], 403);
        }

        if (strtolower((string) $user->status) === 'inactive') {
            return response()->json([
                'status' => false,
                'message' => 'Your employee account is inactive. Please contact an administrator.',
            ], 403);
        }

        return $next($request);
    }
}
