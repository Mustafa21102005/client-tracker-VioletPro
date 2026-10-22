<?php

namespace App\Http\Controllers;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use App\Models\ChangeRequest;
use App\Http\Requests\StoreChangeRequestRequest;
use App\Http\Requests\UpdateChangeRequestRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChangeRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $status = $request->enum('status', ChangeRequestStatus::class);
        $priority = $request->enum('priority', Priority::class);

        $payment = $request->string('payment')->toString();
        $payment = in_array($payment, ['paid', 'unpaid', 'free'], true) ? $payment : null;

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['oldest', 'price_desc', 'price_asc'], true) ? $sort : 'newest';

        $cancelled = ChangeRequestStatus::Cancelled->value;

        $query = ChangeRequest::query()
            ->when($search !== '', fn($query) => $query->search($search))
            ->when($status, fn($query) => $query->where('status', $status->value))
            ->when($priority, fn($query) => $query->where('priority', $priority->value))
            ->when($payment, fn($query) => match ($payment) {
                'paid' => $query->whereNotNull('paid_at'),
                // "Unpaid" matches the dashboard: has a price, no payment date, not cancelled.
                'unpaid' => $query->whereNotNull('price')->whereNull('paid_at')->where('status', '!=', $cancelled),
                'free' => $query->whereNull('price'),
            });

        $edits = (clone $query)
            ->with(['project:id,company_id,name', 'project.company:id,client_id,name', 'project.company.client:id,name', 'attachments.media'])
            ->tap(fn($query) => match ($sort) {
                'oldest' => $query->oldest()->oldest('id'),
                'price_desc' => $query->orderByRaw('price IS NULL')->orderByDesc('price')->latest('id'),
                'price_asc' => $query->orderByRaw('price IS NULL')->orderBy('price')->latest('id'),
                default => $query->latest()->latest('id'),
            })
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('change-requests/Index', [
            'edits' => $edits,
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
                'priority' => $priority?->value,
                'payment' => $payment,
                'sort' => $sort,
            ],
            // Totals cover the whole filtered set, not just the page on screen.
            'summary' => [
                'value' => (float) (clone $query)->where('status', '!=', $cancelled)->sum('price'),
                'unpaid' => (float) (clone $query)->where('status', '!=', $cancelled)->whereNull('paid_at')->sum('price'),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChangeRequestRequest $request, Project $project): RedirectResponse
    {
        $project->changeRequests()->create($request->validated());

        return back()->with('success', 'Edit added successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChangeRequestRequest $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $changeRequest->update($request->validated());

        return back()->with('success', 'Edit updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChangeRequest $changeRequest): RedirectResponse
    {
        $changeRequest->delete();

        return back()->with('success', 'Edit deleted successfully!');
    }

    public function markPaid(ChangeRequest $changeRequest): RedirectResponse
    {
        if ($changeRequest->price === null) {
            return back()->with('error', 'Add a price before marking this request as paid.');
        }

        $changeRequest->update(['paid_at' => now()]);

        return back()->with('success', 'Marked as paid successfully!');
    }

    public function markDone(ChangeRequest $changeRequest): RedirectResponse
    {
        $changeRequest->update(['status' => ChangeRequestStatus::Done]);

        return back()->with('success', 'Marked as done successfully!');
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $edits = ChangeRequest::query()
            ->with('project.company.client')
            ->when($term !== '', fn($query) => $query->search($term))
            ->latest()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn(ChangeRequest $edit) => [
                'id' => $edit->id,
                'title' => $edit->title,
                'subtitle' => collect([
                    $edit->project?->company?->client?->name,
                    $edit->project?->company?->name,
                    $edit->project?->name,
                ])->filter()->join(' · '),
            ]);

        return response()->json($edits);
    }
}
