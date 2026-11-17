<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\CompanySettingResource;
use App\Models\CompanySetting;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function show(): JsonResponse
    {
        Gate::authorize('view', CompanySetting::class);

        // Explicit 200: a resource returns 201 when its model was just created by current().
        return (new CompanySettingResource(CompanySetting::current()))->response()->setStatusCode(200);
    }

    /**
     * POST, not PUT: PHP only fills file uploads for POST requests.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = CompanySetting::current();
        Gate::authorize('update', $settings);

        $updated = $this->settings->update($request->validated(), $request->file('logo'));

        return (new CompanySettingResource($updated))->response()->setStatusCode(200);
    }
}
