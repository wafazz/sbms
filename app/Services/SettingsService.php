<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, ?UploadedFile $logo = null): CompanySetting
    {
        return DB::transaction(function () use ($data, $logo) {
            $settings = CompanySetting::current();

            $fields = collect($data)->except('logo')->all();

            if ($logo) {
                if ($settings->logo_path) {
                    Storage::disk('public')->delete($settings->logo_path);
                }
                $fields['logo_path'] = $logo->store('logos', 'public');
            }

            $settings->update($fields);

            return $settings->refresh();
        });
    }
}
