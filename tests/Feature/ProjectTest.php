<?php

use App\Models\Activity;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;

function projectPayload(Client $client, array $overrides = []): array
{
    return array_merge([
        'client_id' => $client->id,
        'name' => 'Website Revamp',
        'description' => 'New marketing site',
        'status' => 'planning',
        'priority' => 'high',
        'start_date' => '2026-12-01',
        'end_date' => '2027-01-31',
        'budget' => 42000.5,
    ], $overrides);
}

it('creates a project with an auto code and logs the activity', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();

    $response = $this->actAs($staff, 'web')
        ->postJson('/api/v1/projects', projectPayload($client))
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'planning')
        ->assertJsonPath('data.client.id', $client->id);

    $code = $response->json('data.code');
    expect($code)->toMatch('/^PRJ-\d{4}-001$/');
    expect(Activity::where('project_id', $response->json('data.id'))->where('action', 'created')->exists())->toBeTrue();
});

it('numbers projects sequentially within the year', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();

    $first = $this->actAs($staff, 'web')->postJson('/api/v1/projects', projectPayload($client))->json('data.code');
    $second = $this->actAs($staff, 'web')->postJson('/api/v1/projects', projectPayload($client, ['name' => 'Two']))->json('data.code');

    $year = now()->format('Y');
    expect($first)->toBe("PRJ-{$year}-001")
        ->and($second)->toBe("PRJ-{$year}-002");
});

it('rejects an end date before the start date', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();

    $this->actAs($staff, 'web')
        ->postJson('/api/v1/projects', projectPayload($client, ['start_date' => '2026-05-10', 'end_date' => '2026-05-01']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

it('rejects a client that was deleted or does not exist', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();
    $client->delete();

    $this->actAs($staff, 'web')
        ->postJson('/api/v1/projects', projectPayload($client))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_id']);
});

it('logs a status change and filters the list by status', function () {
    $staff = User::factory()->create();
    $project = Project::factory()->create(['status' => 'planning']);

    $this->actAs($staff, 'web')
        ->putJson("/api/v1/projects/{$project->id}", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'active');

    expect(Activity::where('project_id', $project->id)->where('action', 'status_changed')->exists())->toBeTrue();

    Project::factory()->create(['status' => 'on_hold']);

    $this->actAs($staff, 'web')
        ->getJson('/api/v1/projects?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $project->id);
});

it('sorts and paginates the project list', function () {
    $staff = User::factory()->create();
    Project::factory()->count(17)->create();

    $this->actAs($staff, 'web')
        ->getJson('/api/v1/projects?sort=name&dir=asc')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 17);
});

it('ignores unsupported sort columns with a validation error', function () {
    $staff = User::factory()->create();

    $this->actAs($staff, 'web')
        ->getJson('/api/v1/projects?sort=password')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});

it('turns the progress override on when progress is set by hand', function () {
    $staff = User::factory()->create();
    $project = Project::factory()->create(['progress' => 0]);

    $this->actAs($staff, 'web')
        ->putJson("/api/v1/projects/{$project->id}", ['progress' => 65])
        ->assertOk()
        ->assertJsonPath('data.progress', 65)
        ->assertJsonPath('data.progress_override', true);
});

it('shows a project with its recent activity', function () {
    $staff = User::factory()->create();
    $project = Project::factory()->create();
    Activity::create(['project_id' => $project->id, 'user_id' => $staff->id, 'action' => 'created', 'description' => 'Hello']);

    $this->actAs($staff, 'web')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('activities.0.description', 'Hello');
});

it('lets staff edit but not delete a project', function () {
    $staff = User::factory()->create();
    $project = Project::factory()->create(['name' => 'Old']);

    $this->actAs($staff, 'web')
        ->putJson("/api/v1/projects/{$project->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New');

    $this->actAs($staff, 'web')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertStatus(403);

    expect($project->fresh())->not->toBeNull();
});

it('lets an admin soft delete a project', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::factory()->create();

    $this->actAs($admin, 'web')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertOk();

    $this->assertSoftDeleted('projects', ['id' => $project->id]);
});
