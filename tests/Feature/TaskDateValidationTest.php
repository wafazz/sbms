<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->project = Project::factory()->create([
        'start_date' => '2026-12-01',
        'end_date' => '2027-01-31',
    ]);
});

it('creates a task inside the project range and logs it', function () {
    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Design',
            'start_date' => '2026-12-05',
            'end_date' => '2026-12-20',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Design')
        ->assertJsonPath('data.status.value', 'todo');

    expect(Activity::where('project_id', $this->project->id)->where('action', 'task_added')->exists())->toBeTrue();
});

it('rejects a task that starts before the project', function () {
    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Early',
            'start_date' => '2026-11-20',
            'end_date' => '2026-12-10',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);
});

it('rejects a task that ends after the project', function () {
    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Late',
            'start_date' => '2027-01-20',
            'end_date' => '2027-02-10',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

it('rejects an end date before the start date', function () {
    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Backwards',
            'start_date' => '2026-12-20',
            'end_date' => '2026-12-10',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

it('persists a drag or resize from the Gantt chart and logs the reschedule', function () {
    $task = ProjectTask::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-12-05',
        'end_date' => '2026-12-10',
    ]);

    $this->actAs($this->staff, 'web')
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/schedule", [
            'start_date' => '2026-12-08',
            'end_date' => '2026-12-18',
        ])
        ->assertOk()
        ->assertJsonPath('data.start_date', '2026-12-08')
        ->assertJsonPath('data.end_date', '2026-12-18');

    expect($task->fresh()->end_date->toDateString())->toBe('2026-12-18');
    expect(Activity::where('action', 'task_rescheduled')->exists())->toBeTrue();
});

it('rejects a drag that moves the task outside the project range', function () {
    $task = ProjectTask::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-12-05',
        'end_date' => '2026-12-10',
    ]);

    $this->actAs($this->staff, 'web')
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/schedule", [
            'start_date' => '2026-11-25',
            'end_date' => '2026-12-10',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);

    expect($task->fresh()->start_date->toDateString())->toBe('2026-12-05');
});

it('does not allow a task in another project to be scheduled through this project', function () {
    $other = Project::factory()->create(['start_date' => '2026-12-01', 'end_date' => '2027-01-31']);
    $task = ProjectTask::factory()->create(['project_id' => $other->id, 'start_date' => '2026-12-05', 'end_date' => '2026-12-10']);

    $this->actAs($this->staff, 'web')
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/schedule", [
            'start_date' => '2026-12-06',
            'end_date' => '2026-12-11',
        ])
        ->assertStatus(404);
});

it('rejects a parent task from another project', function () {
    $other = Project::factory()->create(['start_date' => '2026-12-01', 'end_date' => '2027-01-31']);
    $foreignParent = ProjectTask::factory()->create(['project_id' => $other->id]);

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Child',
            'start_date' => '2026-12-05',
            'end_date' => '2026-12-06',
            'parent_id' => $foreignParent->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

it('marks a done task as 100 percent unless a value is given', function () {
    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Finished',
            'start_date' => '2026-12-05',
            'end_date' => '2026-12-06',
            'status' => 'done',
        ])
        ->assertCreated()
        ->assertJsonPath('data.progress', 100);
});

it('flags unfinished tasks past their end date as overdue', function () {
    $task = ProjectTask::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(3)->toDateString(),
        'status' => 'in_progress',
    ]);

    $this->actAs($this->staff, 'web')
        ->getJson("/api/v1/projects/{$this->project->id}/tasks")
        ->assertOk()
        ->assertJsonPath('data.0.overdue', true);
});
