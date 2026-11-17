<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MilestoneResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectTimelineController extends Controller
{
    /** Everything the Gantt chart needs, in one request. */
    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $tasks = $project->tasks()->with('assignee:id,name')->orderBy('start_date')->orderBy('sort_order')->get();
        $milestones = $project->milestones()->orderBy('due_date')->get();

        return response()->json([
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'start_date' => $project->start_date->toDateString(),
                    'end_date' => $project->end_date->toDateString(),
                    'progress' => $project->progress,
                ],
                'today' => now()->toDateString(),
                'tasks' => TaskResource::collection($tasks)->resolve(),
                'milestones' => MilestoneResource::collection($milestones)->resolve(),
            ],
        ]);
    }
}
