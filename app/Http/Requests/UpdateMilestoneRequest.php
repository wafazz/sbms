<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ProjectPolicy is checked in the controller.
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'due_date' => ['sometimes', 'date'],
            'is_completed' => ['sometimes', 'boolean'],
        ];
    }
}
