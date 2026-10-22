<?php

namespace App\Http\Controllers;

use App\Enum\ChangeRequestStatus;
use App\Models\Project;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request, Company $company): RedirectResponse
    {
        $company->projects()->create($request->validated());

        return back()->with('success', 'Project added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project): Response
    {
        $project->load(['company:id,name,client_id', 'company.client:id,name']);

        $active = fn() => $project->changeRequests()
            ->where('status', '!=', ChangeRequestStatus::Cancelled->value);

        return Inertia::render('projects/Show', [
            'project' => $project,
            'changeRequests' => $project->changeRequests()
                ->with('attachments.media')
                ->latest()
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'stats' => [
                'total' => $project->changeRequests()->count(),
                'pending' => $project->changeRequests()->where('status', ChangeRequestStatus::Pending->value)->count(),
                'done' => $project->changeRequests()->where('status', ChangeRequestStatus::Done->value)->count(),
                'active' => $active()->count(),
                'total_value' => (float) $active()->sum('price'),
                'unpaid_value' => (float) $active()->whereNull('paid_at')->sum('price'),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return back()->with('success', 'Project updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        if ($project->changeRequests()->exists()) {
            return back()->with('error', "Delete this project's edits first.");
        }

        $project->delete();

        return back()->with('success', 'Project deleted successfully!');
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $projects = Project::query()
            ->with('company.client')
            ->when($term !== '', function ($query) use ($term) {
                $like = "%{$term}%";

                $query->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhereHas('company', fn($q) => $q->where('name', 'like', $like))
                        ->orWhereHas('company.client', fn($q) => $q->where('name', 'like', $like));
                });
            })
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn(Project $project) => [
                'id' => $project->id,
                'title' => $project->name,
                'subtitle' => collect([$project->company?->name, $project->company?->client?->name])->filter()->join(' · '),
            ]);

        return response()->json($projects);
    }
}
