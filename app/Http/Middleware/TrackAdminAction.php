<?php

namespace App\Http\Middleware;

use App\Services\AdminActionNotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAdminAction
{
    public function __construct(private readonly AdminActionNotificationService $notifications) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shouldTrack = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $employeeIds = $shouldTrack ? $this->notifications->recipientsBefore($request) : [];
        $response = $next($request);

        if ($shouldTrack && $response->getStatusCode() >= 200 && $response->getStatusCode() < 400) {
            $this->notifications->record($request, $response, $employeeIds);
        }

        return $response;
    }
}
