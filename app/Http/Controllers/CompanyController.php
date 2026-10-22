<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request, Client $client): RedirectResponse
    {
        $client->companies()->create($request->validated());

        return back()->with('success', 'Company added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company): Response
    {
        $company->load('client:id,name');

        return Inertia::render('companies/Show', [
            'company' => $company,
            'projects' => $company->projects()
                ->withCount('changeRequests')
                ->latest()
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'changeRequestsCount' => $company->changeRequests()->count(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return back()->with('success', 'Company updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company): RedirectResponse
    {
        if ($company->projects()->exists()) {
            return back()->with('error', "Delete this company's projects first.");
        }

        $company->delete();

        return back()->with('success', 'Company deleted successfully!');
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $companies = Company::query()
            ->with('client:id,name')
            ->when($term !== '', function ($query) use ($term) {
                $like = "%{$term}%";

                $query->where(fn($q) => $q->where('name', 'like', $like)
                    ->orWhereHas('client', fn($q) => $q->where('name', 'like', $like)));
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'client_id', 'name'])
            ->map(fn(Company $company) => [
                'id' => $company->id,
                'title' => $company->name,
                'subtitle' => $company->client?->name,
            ]);

        return response()->json($companies);
    }
}
