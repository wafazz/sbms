<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;

class ActivityLogger
{
    public function log(Project $project, ?User $user, string $action, string $description): Activity
    {
        return Activity::create([
            'project_id' => $project->id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
