<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /** Sort keys the client may use. Anything else falls back to the default. */
    private const SORTABLE = ['code', 'name', 'start_date', 'end_date', 'progress', 'budget', 'created_at'];

    public function __construct(private readonly ProjectService $projects) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Project::class);

        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'client_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) $request->query('q', ''));
        $sort = $request->query('sort', 'created_at');
        $dir = $request->query('dir', 'desc');

        $projects = Project::query()
            ->with(['client:id,name,company'])
            ->withCount('tasks')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return ProjectResource::collection($projects);
    }

    /**
     * Project count per status, for the sidebar submenu badges. Every status is returned, even at zero.
     */
    public function statusCounts(): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $counts = Project::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = collect(ProjectStatus::cases())->map(fn (ProjectStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ])->values();

        return response()->json([
            'data' => $statuses,
            'total' => (int) $counts->sum(),
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $project = $this->projects->create($request->validated(), $request->user());

        return (new ProjectResource($project->load('client')))->response()->setStatusCode(201);
    }

    public function show(Project $project)
    {
        Gate::authorize('view', $project);

        $project->load(['client', 'createdBy'])->loadCount('tasks');

        return (new ProjectResource($project))->additional([
            'activities' => ActivityResource::collection(
                $project->activities()->with('user:id,name')->latest()->limit(20)->get()
            ),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        Gate::authorize('update', $project);

        $project = $this->projects->update($project, $request->validated(), $request->user());

        return new ProjectResource($project->load(['client', 'createdBy'])->loadCount('tasks'));
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }
}
