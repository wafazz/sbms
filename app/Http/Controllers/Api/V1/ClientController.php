<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Client::class);

        $search = trim((string) $request->query('q', ''));

        $clients = Client::query()
            ->withCount('projects')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where('name', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhere('email', 'like', $like);
            }))
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        Gate::authorize('create', Client::class);

        $client = Client::create($request->validated());

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        return new ClientResource($client->loadCount('projects'));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        Gate::authorize('update', $client);

        $client->update($request->validated());

        return new ClientResource($client->loadCount('projects'));
    }

    public function destroy(Client $client): JsonResponse
    {
        Gate::authorize('delete', $client);

        if ($client->projects()->exists() || $client->invoices()->exists()) {
            throw ValidationException::withMessages([
                'client' => ['This client has projects or invoices. Keep the record, or move them first.'],
            ]);
        }

        $client->delete();

        return response()->json(['message' => 'Client deleted.']);
    }
}
