<?php

namespace App\Http\Requests;

class UpdateClientRequest extends StoreClientRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => array_merge(['sometimes'], array_values(array_diff($rules, ['required']))),
            parent::rules(),
        );
    }
}
