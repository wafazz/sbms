<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\ProjectTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    /** @use HasFactory<ProjectTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'status',
        'progress',
        'assigned_to',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Overdue: not done and the end date has passed. */
    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->end_date->isPast() && ! $this->end_date->isToday();
    }
}
