<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends StoreProjectRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'client_id' => ['sometimes', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'budget' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            // Manual progress (0-100). Sending it turns the override on.
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            // false returns progress to the automatic task average.
            'progress_override' => ['sometimes', 'boolean'],
        ];

        return $rules;
    }

    /**
     * The end date must be on or after the start date, using the stored start when only end is sent.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $project = $this->route('project');
            $start = $this->input('start_date', $project?->start_date?->toDateString());
            $end = $this->input('end_date', $project?->end_date?->toDateString());

            if ($start && $end && $end < $start) {
                $validator->errors()->add('end_date', 'The end date must be on or after the start date.');
            }
        });
    }
}
