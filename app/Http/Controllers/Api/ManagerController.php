<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:255']]);
        $query = User::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('designationDetails', fn (Builder $designation) => $designation->where('hierarchy_level', 'manager'))
                ->orWhereHas('roles', fn (Builder $role) => $role->where('name', 'like', '%manager%'))
                ->orWhere('designation', 'like', '%manager%'))
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['active']));
        if (! empty($validated['q'])) {
            $term = '%'.trim($validated['q']).'%';
            $query->where(fn (Builder $query) => $query->where('name', 'like', $term)
                ->orWhere('employee_id', 'like', $term)
                ->orWhere('email', 'like', $term));
        }
        $managers = $query->with('designationDetails:id,name,hierarchy_level')
            ->withCount('managedProjects')->orderBy('name')
            ->get(['id', 'employee_id', 'name', 'email', 'mobile_number', 'designation', 'designation_id', 'avatar', 'status']);

        return response()->json([
            'status' => true,
            'message' => 'Managers retrieved successfully.',
            'total' => $managers->count(),
            'data' => $managers,
        ]);
    }
}
