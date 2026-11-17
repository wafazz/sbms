<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MilestoneService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data, User $actor): ProjectMilestone
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $milestone = $project->milestones()->create($data + ['is_completed' => false]);

            $this->activity->log($project, $actor, 'milestone_added', "Milestone added: {$milestone->title}.");

            return $milestone;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, ProjectMilestone $milestone, array $data, User $actor): ProjectMilestone
    {
        return DB::transaction(function () use ($project, $milestone, $data, $actor) {
            $wasCompleted = $milestone->is_completed;

            if (array_key_exists('is_completed', $data)) {
                $data['completed_at'] = $data['is_completed'] ? now() : null;
            }

            $milestone->update($data);

            if (! $wasCompleted && $milestone->is_completed) {
                $this->activity->log($project, $actor, 'milestone_completed', "Milestone completed: {$milestone->title}.");
            } elseif ($wasCompleted && ! $milestone->is_completed) {
                $this->activity->log($project, $actor, 'milestone_reopened', "Milestone reopened: {$milestone->title}.");
            } else {
                $this->activity->log($project, $actor, 'milestone_updated', "Milestone updated: {$milestone->title}.");
            }

            return $milestone->refresh();
        });
    }

    public function delete(Project $project, ProjectMilestone $milestone, User $actor): void
    {
        DB::transaction(function () use ($project, $milestone, $actor) {
            $title = $milestone->title;
            $milestone->delete();

            $this->activity->log($project, $actor, 'milestone_deleted', "Milestone deleted: {$title}.");
        });
    }
}
