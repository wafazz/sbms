<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /** One endpoint for the whole dashboard. Cached for 60 seconds. */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        return response()->json(['data' => $this->dashboard->summary()]);
    }
}
