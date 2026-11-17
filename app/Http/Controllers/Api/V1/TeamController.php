<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Names of active team members for assignee dropdowns. Staff cannot list users,
 * so this exposes only id and name.
 */
class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $members = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $members]);
    }
}
