<?php

namespace App\Http\Controllers;

use App\Enum\ChangeRequestStatus;
use App\Exports\ReportSheet;
use App\Exports\ReportWorkbook;
use App\Http\Requests\ReportRequest;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Reports\Report;
use App\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private const PER_PAGE = 15;

    public function index(ReportRequest $request): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $tab = $request->tab();

        $tabs = collect(Report::TABS)
            ->map(fn(string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'count' => Report::query($key, $filters)->count(),
            ])
            ->values();

        $rows = Report::query($tab, $filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn(Model $model) => Report::row($tab, $model));

        return Inertia::render('reports/Index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'columns' => Report::columns($tab),
            'rows' => $rows,
            'filters' => $filters->toArray(),
            'selected' => $this->selectedOptions($filters),
            'summary' => $tab === 'edits' ? $this->editsSummary($filters) : null,
        ]);
    }

    public function export(ReportRequest $request): BinaryFileResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $scope = $request->input('tab') === 'all' ? 'all' : $request->tab();
        $stamp = now()->format('Y-m-d_His');

        if ($scope === 'all') {
            return Excel::download(new ReportWorkbook($filters), "report_all_{$stamp}.xlsx");
        }

        return Excel::download(new ReportSheet($scope, $filters), "report_{$scope}_{$stamp}.xlsx");
    }

    /** Totals for the Edits tab, over the whole filtered set (not just the page on screen). */
    private function editsSummary(ReportFilters $filters): array
    {
        $active = fn() => Report::query('edits', $filters)
            ->where('status', '!=', ChangeRequestStatus::Cancelled->value);

        $paid = (float) $active()->whereNotNull('paid_at')->sum('price');
        $unpaid = (float) $active()->whereNull('paid_at')->sum('price');

        return [
            'value' => round($paid + $unpaid, 2),
            'paid' => $paid,
            'unpaid' => $unpaid,
        ];
    }

    /** The chosen filter values with their names, so the page can show them as chips. */
    private function selectedOptions(ReportFilters $filters): array
    {
        $context = fn(iterable $parts) => collect($parts)->filter()->join(' · ');

        return [
            'clients' => Client::query()
                ->whereIn('id', $filters->clients)
                ->get(['id', 'name', 'phone'])
                ->map(fn(Client $client) => ['id' => $client->id, 'title' => $client->name, 'subtitle' => $client->phone])
                ->values(),

            'companies' => Company::query()
                ->with('client:id,name')
                ->whereIn('id', $filters->companies)
                ->get(['id', 'client_id', 'name'])
                ->map(fn(Company $company) => ['id' => $company->id, 'title' => $company->name, 'subtitle' => $company->client?->name])
                ->values(),

            'projects' => Project::query()
                ->with('company.client')
                ->whereIn('id', $filters->projects)
                ->get()
                ->map(fn(Project $project) => [
                    'id' => $project->id,
                    'title' => $project->name,
                    'subtitle' => $context([$project->company?->client?->name, $project->company?->name]),
                ])
                ->values(),

            'edits' => ChangeRequest::query()
                ->with('project.company.client')
                ->whereIn('id', $filters->edits)
                ->get()
                ->map(fn(ChangeRequest $edit) => [
                    'id' => $edit->id,
                    'title' => $edit->title,
                    'subtitle' => $context([$edit->project?->company?->client?->name, $edit->project?->company?->name, $edit->project?->name]),
                ])
                ->values(),
        ];
    }
}
