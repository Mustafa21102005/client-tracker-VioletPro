<?php

namespace App\Http\Controllers;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        // "Unpaid" = has a price, no payment date, and was not cancelled.
        // A function, so every use starts from a fresh query.
        $unpaid = fn() => ChangeRequest::query()
            ->where('status', '!=', ChangeRequestStatus::Cancelled->value)
            ->whereNotNull('price')
            ->whereNull('paid_at');

        return Inertia::render('Dashboard', [
            'followUpDays' => Client::FOLLOW_UP_ALERT_DAYS,

            'totals' => [
                'clients' => Client::count(),
                'companies' => Company::count(),
                'projects' => Project::count(),
            ],

            'kpis' => [
                'outstanding' => (float) $unpaid()->sum('price'),
                'outstanding_count' => $unpaid()->count(),
                'collected_this_month' => (float) ChangeRequest::query()
                    ->whereNotNull('paid_at')
                    ->where('paid_at', '>=', now()->startOfMonth())
                    ->sum('price'),
                'pending' => ChangeRequest::where('status', ChangeRequestStatus::Pending->value)->count(),
                'follow_ups_due' => Client::needsFollowUp()->count(),
            ],

            'revenue' => $this->revenueByMonth(),
            'statusCounts' => $this->statusCounts(),

            'followUps' => Client::needsFollowUp()
                ->orderBy('last_follow_up')
                ->limit(5)
                ->get(['id', 'name', 'phone', 'last_follow_up']),

            'unpaid' => $unpaid()
                ->with('project.company.client')
                ->orderByRaw("CASE WHEN status = 'done' THEN 0 ELSE 1 END")
                ->orderByDesc('price')
                ->limit(5)
                ->get()
                ->map(fn(ChangeRequest $request) => $this->summarize($request)),

            'attention' => ChangeRequest::query()
                ->with('project.company.client')
                ->where('status', ChangeRequestStatus::Pending->value)
                ->whereIn('priority', [Priority::High->value, Priority::Urgent->value])
                ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
                ->oldest()
                ->limit(5)
                ->get()
                ->map(fn(ChangeRequest $request) => $this->summarize($request)),
        ]);
    }

    /** Payments received in each of the last 6 months (oldest first). */
    private function revenueByMonth(): array
    {
        $months = collect(range(5, 0))->map(fn(int $ago) => now()->startOfMonth()->subMonths($ago));

        $totals = ChangeRequest::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $months->first())
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(price) as total")
            ->groupBy('month')
            ->toBase()
            ->pluck('total', 'month');

        return $months->map(fn($month) => [
            'label' => $month->format('M'),
            'total' => (float) ($totals[$month->format('Y-m')] ?? 0),
        ])->all();
    }

    /** How many requests sit in each status, with zeros for empty ones. */
    private function statusCounts(): array
    {
        $counts = ChangeRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        return collect(ChangeRequestStatus::cases())
            ->mapWithKeys(fn(ChangeRequestStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    private function summarize(ChangeRequest $request): array
    {
        return [
            'id' => $request->id,
            'title' => $request->title,
            'price' => $request->price === null ? null : (float) $request->price,
            'status' => $request->status,
            'priority' => $request->priority,
            'project_id' => $request->project_id,
            'project' => $request->project?->name,
            'company' => $request->project?->company?->name,
            'client' => $request->project?->company?->client?->name,
        ];
    }
}
