<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkingDayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkingDayController extends Controller
{
    public function __invoke(Request $request, WorkingDayService $workingDays): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Working days calculated successfully.',
            'data' => $workingDays->calculate(
                Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $validated['end_date'])->startOfDay(),
            ),
        ]);
    }
}
