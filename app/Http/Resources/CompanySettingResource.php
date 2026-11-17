<?php

namespace App\Http\Resources;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin CompanySetting
 */
class CompanySettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'tax_number' => $this->tax_number,
            'default_tax_rate' => $this->default_tax_rate,
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'bank_swift' => $this->bank_swift,
        ];
    }
}
