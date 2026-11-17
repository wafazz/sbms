<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectTaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request, Project $project)
    {
        Gate::authorize('view', $project);

        $tasks = $project->tasks()->with('assignee:id,name')->orderBy('start_date')->orderBy('sort_order')->get();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $task = $this->tasks->create($project, $request->validated(), $request->user());

        return (new TaskResource($task->load('assignee:id,name')))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskRequest $request, Project $project, ProjectTask $task): TaskResource
    {
        Gate::authorize('update', $project);

        $task = $this->tasks->update($project, $task, $request->validated(), $request->user());

        return new TaskResource($task->load('assignee:id,name'));
    }

    /** Used by Gantt drag and resize. Dates only. */
    public function schedule(ScheduleTaskRequest $request, Project $project, ProjectTask $task): TaskResource
    {
        Gate::authorize('update', $project);

        $task = $this->tasks->update($project, $task, $request->validated(), $request->user());

        return new TaskResource($task->load('assignee:id,name'));
    }

    public function destroy(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        Gate::authorize('update', $project);

        $this->tasks->delete($project, $task, $request->user());

        return response()->json(['message' => 'Task deleted.']);
    }
}
