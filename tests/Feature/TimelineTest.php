<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->project = Project::factory()->create([
        'start_date' => '2026-12-01',
        'end_date' => '2027-01-31',
    ]);
});

it('progress follows the average of task progress', function () {
    $this->actAs($this->staff, 'web')->postJson("/api/v1/projects/{$this->project->id}/tasks", [
        'title' => 'A', 'start_date' => '2026-12-02', 'end_date' => '2026-12-03', 'progress' => 20,
    ])->assertCreated();

    $this->actAs($this->staff, 'web')->postJson("/api/v1/projects/{$this->project->id}/tasks", [
        'title' => 'B', 'start_date' => '2026-12-02', 'end_date' => '2026-12-03', 'progress' => 60,
    ])->assertCreated();

    expect($this->project->fresh()->progress)->toBe(40);
    expect(Activity::where('action', 'progress_updated')->exists())->toBeTrue();
});

it('progress is not recalculated while overridden', function () {
    $this->actAs($this->staff, 'web')
        ->putJson("/api/v1/projects/{$this->project->id}", ['progress' => 75])
        ->assertOk();

    $this->actAs($this->staff, 'web')->postJson("/api/v1/projects/{$this->project->id}/tasks", [
        'title' => 'C', 'start_date' => '2026-12-02', 'end_date' => '2026-12-03', 'progress' => 0,
    ])->assertCreated();

    expect($this->project->fresh()->progress)->toBe(75)
        ->and($this->project->fresh()->progress_override)->toBeTrue();
});

it('returns to automatic progress when the override is cleared', function () {
    ProjectTask::factory()->create(['project_id' => $this->project->id, 'progress' => 50, 'start_date' => '2026-12-02', 'end_date' => '2026-12-03']);
    $this->project->update(['progress' => 90, 'progress_override' => true]);

    $this->actAs($this->staff, 'web')
        ->putJson("/api/v1/projects/{$this->project->id}", ['progress_override' => false])
        ->assertOk();

    expect($this->project->fresh()->progress)->toBe(50)
        ->and($this->project->fresh()->progress_override)->toBeFalse();
});

it('recalculates progress when a task is deleted', function () {
    $keep = ProjectTask::factory()->create(['project_id' => $this->project->id, 'progress' => 100, 'start_date' => '2026-12-02', 'end_date' => '2026-12-03']);
    $drop = ProjectTask::factory()->create(['project_id' => $this->project->id, 'progress' => 0, 'start_date' => '2026-12-02', 'end_date' => '2026-12-03']);

    $this->actAs($this->staff, 'web')
        ->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$drop->id}")
        ->assertOk();

    expect($this->project->fresh()->progress)->toBe(100);
    expect(ProjectTask::find($keep->id))->not->toBeNull();
});

it('creates, completes and reopens a milestone with activity entries', function () {
    $response = $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$this->project->id}/milestones", [
            'title' => 'Go-live', 'due_date' => '2027-01-15',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_completed', false);

    $id = $response->json('data.id');

    $this->actAs($this->staff, 'web')
        ->putJson("/api/v1/projects/{$this->project->id}/milestones/{$id}", ['is_completed' => true])
        ->assertOk()
        ->assertJsonPath('data.is_completed', true);

    expect(ProjectMilestone::find($id)->completed_at)->not->toBeNull();

    $this->actAs($this->staff, 'web')
        ->putJson("/api/v1/projects/{$this->project->id}/milestones/{$id}", ['is_completed' => false])
        ->assertOk();

    expect(Activity::where('action', 'milestone_completed')->exists())->toBeTrue()
        ->and(Activity::where('action', 'milestone_reopened')->exists())->toBeTrue();
});

it('returns the full timeline payload for the Gantt chart', function () {
    ProjectTask::factory()->create([
        'project_id' => $this->project->id,
        'title' => 'Late task',
        'start_date' => '2020-01-02',
        'end_date' => '2020-01-05',
        'status' => 'todo',
    ]);
    ProjectMilestone::factory()->create(['project_id' => $this->project->id, 'title' => 'Sign-off']);

    $this->actAs($this->staff, 'web')
        ->getJson("/api/v1/projects/{$this->project->id}/timeline")
        ->assertOk()
        ->assertJsonPath('data.project.start_date', '2026-12-01')
        ->assertJsonPath('data.project.end_date', '2027-01-31')
        ->assertJsonPath('data.tasks.0.title', 'Late task')
        ->assertJsonPath('data.tasks.0.overdue', true)
        ->assertJsonPath('data.milestones.0.title', 'Sign-off')
        ->assertJsonStructure(['data' => ['today', 'project', 'tasks', 'milestones']]);
});

it('forbids guests from the timeline', function () {
    $this->getJson("/api/v1/projects/{$this->project->id}/timeline")->assertStatus(401);
});
