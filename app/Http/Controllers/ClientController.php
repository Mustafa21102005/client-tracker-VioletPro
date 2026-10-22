<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\ChangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $clients = Client::query()
            ->withCount(['companies', 'projects'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        Client::create($request->validated());

        return back()->with('success', 'Client added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client): Response
    {
        $client->loadCount(['companies', 'projects']);

        return Inertia::render('clients/Show', [
            'client' => $client,
            'companies' => $client->companies()
                ->withCount('projects')
                ->latest()
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'changeRequestsCount' => ChangeRequest::whereHas(
                'project.company',
                fn($query) => $query->where('client_id', $client->id),
            )->count(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return back()->with('success', 'Client updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client): RedirectResponse
    {
        if ($client->companies()->exists()) {
            return back()->with('error', "Delete this client's companies first.");
        }

        $client->delete();

        return back()->with('success', 'Client deleted successfully!');
    }

    public function followUp(Client $client): RedirectResponse
    {
        $client->update(['last_follow_up' => today()]);

        return back()->with('success', "{$client->name} marked as followed up today.");
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $clients = Client::query()
            ->when($term !== '', function ($query) use ($term) {
                $like = "%{$term}%";

                $query->where(fn($q) => $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone'])
            ->map(fn(Client $client) => [
                'id' => $client->id,
                'title' => $client->name,
                'subtitle' => $client->phone,
            ]);

        return response()->json($clients);
    }
}
