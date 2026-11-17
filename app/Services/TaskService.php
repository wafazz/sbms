<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data, User $actor): ProjectTask
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $data = $this->normalise($data);
            $this->assertWithinProject($project, $data['start_date'], $data['end_date']);
            $this->assertParent($project, null, $data['parent_id'] ?? null);

            $task = $project->tasks()->create($data);

            $this->activity->log($project, $actor, 'task_added', "Task added: {$task->title}.");
            $this->projects->recalculateProgress($project, $actor);

            return $task->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, ProjectTask $task, array $data, User $actor): ProjectTask
    {
        return DB::transaction(function () use ($project, $task, $data, $actor) {
            $data = $this->normalise($data);

            $start = $data['start_date'] ?? $task->start_date;
            $end = $data['end_date'] ?? $task->end_date;
            $this->assertWithinProject($project, $start, $end);
            $this->assertParent($project, $task, $data['parent_id'] ?? $task->parent_id);

            $datesChanged = (isset($data['start_date']) && $this->day($data['start_date']) !== $task->start_date->toDateString())
                || (isset($data['end_date']) && $this->day($data['end_date']) !== $task->end_date->toDateString());

            $task->update($data);

            $this->activity->log(
                $project,
                $actor,
                $datesChanged ? 'task_rescheduled' : 'task_updated',
                $datesChanged
                    ? "Task rescheduled: {$task->title} ({$task->start_date->toDateString()} to {$task->end_date->toDateString()})."
                    : "Task updated: {$task->title}.",
            );
            $this->projects->recalculateProgress($project, $actor);

            return $task->refresh();
        });
    }

    public function delete(Project $project, ProjectTask $task, User $actor): void
    {
        DB::transaction(function () use ($project, $task, $actor) {
            $title = $task->title;
            $task->delete();

            $this->activity->log($project, $actor, 'task_deleted', "Task deleted: {$title}.");
            $this->projects->recalculateProgress($project, $actor);
        });
    }

    /**
     * A task's dates must sit inside the project's date range. The backend is the authority.
     */
    public function assertWithinProject(Project $project, CarbonInterface|string $start, CarbonInterface|string $end): void
    {
        $start = $this->day($start);
        $end = $this->day($end);
        $projectStart = $project->start_date->toDateString();
        $projectEnd = $project->end_date->toDateString();
        $errors = [];

        if ($end < $start) {
            $errors['end_date'] = ['The end date must be on or after the start date.'];
        }
        if ($start < $projectStart) {
            $errors['start_date'] = ["The task must start on or after the project start ({$projectStart})."];
        }
        if ($end > $projectEnd) {
            $errors['end_date'] = ["The task must end on or before the project end ({$projectEnd})."];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A parent must be another task in the same project, and never the task itself.
     */
    private function assertParent(Project $project, ?ProjectTask $task, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($task && $task->id === $parentId) {
            throw ValidationException::withMessages(['parent_id' => ['A task cannot be its own parent.']]);
        }

        $belongs = ProjectTask::query()
            ->where('project_id', $project->id)
            ->whereKey($parentId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages(['parent_id' => ['The parent task must belong to this project.']]);
        }
    }

    /**
     * Marking a task done sets its progress to 100 unless a value is given.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        if (($data['status'] ?? null) === TaskStatus::Done->value && ! array_key_exists('progress', $data)) {
            $data['progress'] = 100;
        }

        return $data;
    }

    private function day(CarbonInterface|string $value): string
    {
        return $value instanceof CarbonInterface
            ? $value->toDateString()
            : Carbon::parse($value)->toDateString();
    }
}
