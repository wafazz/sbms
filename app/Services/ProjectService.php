<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            $year = (int) now()->format('Y');
            $number = $this->sequences->next("project:{$year}");

            // The code is system-generated, so it is set with forceFill and is not mass-assignable.
            $project = (new Project)->forceFill([
                ...$data,
                'code' => sprintf('PRJ-%d-%03d', $year, $number),
                'progress' => 0,
                'progress_override' => false,
                'created_by' => $actor->id,
            ]);
            $project->save();

            $this->activity->log($project, $actor, 'created', "Project {$project->code} created.");

            return $project->refresh();
        });
    }

    /**
     * Update project fields and log meaningful changes.
     * Setting progress by hand turns on the override. Sending progress_override=false returns it to automatic.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $oldStatus = $project->status;

            if (array_key_exists('progress', $data)) {
                $data['progress_override'] = $data['progress_override'] ?? true;
            }

            $project->update($data);

            // Clearing the override must bring back the task average straight away.
            $this->recalculateProgress($project, $actor);

            if ($project->status !== $oldStatus) {
                $this->activity->log(
                    $project,
                    $actor,
                    'status_changed',
                    "Status changed from {$this->label($oldStatus)} to {$this->label($project->status)}.",
                );
            }

            if (array_key_exists('progress', $data) && $project->progress_override) {
                $this->activity->log($project, $actor, 'progress_overridden', "Progress set manually to {$project->progress}%.");
            }

            if (! $project->wasChanged(['status', 'progress', 'progress_override']) && $project->wasChanged()) {
                $this->activity->log($project, $actor, 'updated', 'Project details updated.');
            }

            return $project->refresh();
        });
    }

    /**
     * Automatic progress is the average of task progress. Ignored when overridden.
     */
    public function recalculateProgress(Project $project, ?User $actor = null): void
    {
        if ($project->progress_override) {
            return;
        }

        $count = $project->tasks()->count();
        if ($count === 0) {
            return;
        }

        $average = (int) round((float) $project->tasks()->avg('progress'));

        if ($average !== $project->progress) {
            $project->update(['progress' => $average]);
            $this->activity->log($project, $actor, 'progress_updated', "Progress recalculated to {$average}% from tasks.");
        }
    }

    private function label(ProjectStatus|string $status): string
    {
        $value = $status instanceof ProjectStatus ? $status : ProjectStatus::from($status);

        return $value->label();
    }
}
