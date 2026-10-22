<?php

namespace App\Reports;

use App\Enum\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Report
{
    public const TABS = [
        'edits' => 'Edits',
        'clients' => 'Clients',
        'companies' => 'Companies',
        'projects' => 'Projects',
    ];

    public static function query(string $tab, ReportFilters $filters): Builder
    {
        return match ($tab) {
            'edits' => self::edits($filters),
            'clients' => self::clients($filters),
            'companies' => self::companies($filters),
            'projects' => self::projects($filters),
        };
    }

    public static function columns(string $tab): array
    {
        return match ($tab) {
            'edits' => [
                self::column('title', 'Edit', link: true),
                self::column('client', 'Client'),
                self::column('company', 'Company'),
                self::column('project', 'Project'),
                self::column('priority', 'Priority', 'priority'),
                self::column('status', 'Status', 'status'),
                self::column('price', 'Price (USD)', 'money'),
                self::column('paid_at', 'Paid on', 'date'),
                self::column('created_at', 'Created', 'date'),
            ],
            'clients' => [
                self::column('name', 'Client', link: true),
                self::column('phone', 'Phone'),
                self::column('email', 'Email'),
                self::column('last_follow_up', 'Last follow-up', 'date'),
                self::column('companies', 'Companies', 'number'),
                self::column('projects', 'Projects', 'number'),
                self::column('created_at', 'Created', 'date'),
            ],
            'companies' => [
                self::column('name', 'Company', link: true),
                self::column('client', 'Client'),
                self::column('projects', 'Projects', 'number'),
                self::column('edits', 'Edits', 'number'),
                self::column('value', 'Value (USD)', 'money'),
                self::column('unpaid', 'Unpaid (USD)', 'money'),
                self::column('created_at', 'Created', 'date'),
            ],
            'projects' => [
                self::column('name', 'Project', link: true),
                self::column('company', 'Company'),
                self::column('client', 'Client'),
                self::column('edits', 'Edits', 'number'),
                self::column('value', 'Value (USD)', 'money'),
                self::column('unpaid', 'Unpaid (USD)', 'money'),
                self::column('created_at', 'Created', 'date'),
            ],
        };
    }

    /** One row of data, keyed by column key. "_url" is where the name links to on screen. */
    public static function row(string $tab, Model $model): array
    {
        return match ($tab) {
            'edits' => [
                'id' => $model->id,
                'title' => $model->title,
                'client' => $model->project?->company?->client?->name,
                'company' => $model->project?->company?->name,
                'project' => $model->project?->name,
                'priority' => $model->priority->value,
                'status' => $model->status->value,
                'price' => $model->price === null ? null : (float) $model->price,
                'paid_at' => $model->paid_at?->format('Y-m-d'),
                'created_at' => $model->created_at?->format('Y-m-d'),
                '_url' => $model->project_id ? "/projects/{$model->project_id}" : null,
            ],
            'clients' => [
                'id' => $model->id,
                'name' => $model->name,
                'phone' => $model->phone,
                'email' => $model->email,
                'last_follow_up' => $model->last_follow_up?->format('Y-m-d'),
                'companies' => $model->companies_count,
                'projects' => $model->projects_count,
                'created_at' => $model->created_at?->format('Y-m-d'),
                '_url' => "/clients/{$model->id}",
            ],
            'companies' => [
                'id' => $model->id,
                'name' => $model->name,
                'client' => $model->client?->name,
                'projects' => $model->projects_count,
                'edits' => $model->change_requests_count,
                'value' => (float) ($model->total_value ?? 0),
                'unpaid' => (float) ($model->unpaid_value ?? 0),
                'created_at' => $model->created_at?->format('Y-m-d'),
                '_url' => "/companies/{$model->id}",
            ],
            'projects' => [
                'id' => $model->id,
                'name' => $model->name,
                'company' => $model->company?->name,
                'client' => $model->company?->client?->name,
                'edits' => $model->change_requests_count,
                'value' => (float) ($model->total_value ?? 0),
                'unpaid' => (float) ($model->unpaid_value ?? 0),
                'created_at' => $model->created_at?->format('Y-m-d'),
                '_url' => "/projects/{$model->id}",
            ],
        };
    }

    private static function column(string $key, string $label, string $type = 'text', bool $link = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'link' => $link];
    }

    private static function edits(ReportFilters $f): Builder
    {
        $query = ChangeRequest::query()
            ->with('project.company.client')
            ->when($f->clients, fn($q) => $q->whereHas('project.company', fn($q) => $q->whereIn('client_id', $f->clients)))
            ->when($f->companies, fn($q) => $q->whereHas('project', fn($q) => $q->whereIn('company_id', $f->companies)))
            ->when($f->projects, fn($q) => $q->whereIn('project_id', $f->projects))
            ->when($f->edits, fn($q) => $q->whereIn('id', $f->edits));

        $f->applyDates($query);

        return $query->latest()->latest('id');
    }

    private static function projects(ReportFilters $f): Builder
    {
        $notCancelled = fn($q) => $q->where('change_requests.status', '!=', ChangeRequestStatus::Cancelled->value);

        $query = Project::query()
            ->with('company.client')
            ->withCount('changeRequests')
            ->withSum(['changeRequests as total_value' => $notCancelled], 'price')
            ->withSum(['changeRequests as unpaid_value' => fn($q) => $notCancelled($q)->whereNull('change_requests.paid_at')], 'price')
            ->when($f->clients, fn($q) => $q->whereHas('company', fn($q) => $q->whereIn('client_id', $f->clients)))
            ->when($f->companies, fn($q) => $q->whereIn('company_id', $f->companies))
            ->when($f->projects, fn($q) => $q->whereIn('projects.id', $f->projects))
            ->when($f->edits, fn($q) => $q->whereHas('changeRequests', fn($q) => $q->whereIn('id', $f->edits)));

        $f->applyDates($query);

        return $query->latest()->latest('id');
    }

    private static function companies(ReportFilters $f): Builder
    {
        $notCancelled = fn($q) => $q->where('change_requests.status', '!=', ChangeRequestStatus::Cancelled->value);

        $query = Company::query()
            ->with('client')
            ->withCount(['projects', 'changeRequests'])
            ->withSum(['changeRequests as total_value' => $notCancelled], 'price')
            ->withSum(['changeRequests as unpaid_value' => fn($q) => $notCancelled($q)->whereNull('change_requests.paid_at')], 'price')
            ->when($f->clients, fn($q) => $q->whereIn('client_id', $f->clients))
            ->when($f->companies, fn($q) => $q->whereIn('companies.id', $f->companies))
            ->when($f->projects, fn($q) => $q->whereHas('projects', fn($q) => $q->whereIn('id', $f->projects)))
            ->when($f->edits, fn($q) => $q->whereHas('projects.changeRequests', fn($q) => $q->whereIn('id', $f->edits)));

        $f->applyDates($query);

        return $query->latest()->latest('id');
    }

    private static function clients(ReportFilters $f): Builder
    {
        $query = Client::query()
            ->withCount(['companies', 'projects'])
            ->when($f->clients, fn($q) => $q->whereIn('clients.id', $f->clients))
            ->when($f->companies, fn($q) => $q->whereHas('companies', fn($q) => $q->whereIn('id', $f->companies)))
            ->when($f->projects, fn($q) => $q->whereHas('companies.projects', fn($q) => $q->whereIn('id', $f->projects)))
            ->when($f->edits, fn($q) => $q->whereHas('companies.projects.changeRequests', fn($q) => $q->whereIn('id', $f->edits)));

        $f->applyDates($query);

        return $query->latest()->latest('id');
    }
}
