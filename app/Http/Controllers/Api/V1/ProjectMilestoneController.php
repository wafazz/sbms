<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\UpdateMilestoneRequest;
use App\Http\Resources\MilestoneResource;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectMilestoneController extends Controller
{
    public function __construct(private readonly MilestoneService $milestones) {}

    public function index(Project $project)
    {
        Gate::authorize('view', $project);

        return MilestoneResource::collection($project->milestones()->orderBy('due_date')->get());
    }

    public function store(StoreMilestoneRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $milestone = $this->milestones->create($project, $request->validated(), $request->user());

        return (new MilestoneResource($milestone))->response()->setStatusCode(201);
    }

    public function update(UpdateMilestoneRequest $request, Project $project, ProjectMilestone $milestone): MilestoneResource
    {
        Gate::authorize('update', $project);

        return new MilestoneResource($this->milestones->update($project, $milestone, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        Gate::authorize('update', $project);

        $this->milestones->delete($project, $milestone, $request->user());

        return response()->json(['message' => 'Milestone deleted.']);
    }
}
