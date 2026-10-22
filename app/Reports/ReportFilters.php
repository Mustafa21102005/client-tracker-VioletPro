<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ReportFilters
{
    /**
     * @param  list<int>  $clients
     * @param  list<int>  $companies
     * @param  list<int>  $projects
     * @param  list<int>  $edits
     */
    public function __construct(
        public readonly array $clients = [],
        public readonly array $companies = [],
        public readonly array $projects = [],
        public readonly array $edits = [],
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $ids = fn(string $key): array => collect($request->input($key, []))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return new self(
            clients: $ids('clients'),
            companies: $ids('companies'),
            projects: $ids('projects'),
            edits: $ids('edits'),
            from: $request->filled('from') ? $request->string('from')->toString() : null,
            to: $request->filled('to') ? $request->string('to')->toString() : null,
        );
    }

    /** The filters as plain data, so the page can show exactly what was applied. */
    public function toArray(): array
    {
        return [
            'clients' => $this->clients,
            'companies' => $this->companies,
            'projects' => $this->projects,
            'edits' => $this->edits,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }

    /** Keeps only rows created inside the date range (both ends included). */
    public function applyDates(Builder $query): void
    {
        $column = $query->getModel()->qualifyColumn('created_at');

        $query
            ->when($this->from, fn($q, $from) => $q->where($column, '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($this->to, fn($q, $to) => $q->where($column, '<=', CarbonImmutable::parse($to)->endOfDay()));
    }
}
