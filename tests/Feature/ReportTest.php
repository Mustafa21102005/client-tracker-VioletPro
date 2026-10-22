<?php

namespace Tests\Feature;

use App\Enum\ChangeRequestStatus;
use App\Exports\ReportSheet;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Reports\Report;
use App\Reports\ReportFilters;
use Closure;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    private function money(float $expected): Closure
    {
        return fn($value) => abs((float) $value - $expected) < 0.001;
    }

    // One full chain: client -> company -> project -> edit, all named after the prefix.
    private function makeBranch(string $prefix): array
    {
        $client = Client::factory()->create(['name' => "{$prefix} Client"]);
        $company = Company::factory()->for($client)->create(['name' => "{$prefix} Co"]);
        $project = Project::factory()->for($company)->create(['name' => "{$prefix} Project"]);
        $edit = ChangeRequest::factory()->for($project)->priced(500)->create(['title' => "{$prefix} Edit"]);

        return compact('client', 'company', 'project', 'edit');
    }

    // The number shown on each tab, for the given filters.
    private function counts(array $params = []): array
    {
        $counts = [];

        $this->get('/reports?' . http_build_query($params))
            ->assertInertia(function (Assert $page) use (&$counts) {
                $page->where('tabs', function ($tabs) use (&$counts) {
                    $counts = collect($tabs)->pluck('count', 'key')->all();

                    return true;
                });
            });

        return $counts;
    }

    public static function tabs(): array
    {
        return [
            'edits' => ['edits'],
            'clients' => ['clients'],
            'companies' => ['companies'],
            'projects' => ['projects'],
        ];
    }

    // ---- The page ----

    public function test_the_page_opens_on_the_edits_tab_with_four_tabs(): void
    {
        $this->makeBranch('Alpha');
        $this->makeBranch('Beta');

        $this->get('/reports')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('reports/Index')
                ->where('tab', 'edits')
                ->has('tabs', 4)
                ->where('rows.total', 2));

        $this->assertSame(['edits' => 2, 'clients' => 2, 'companies' => 2, 'projects' => 2], $this->counts());
    }

    #[DataProvider('tabs')]
    public function test_no_tab_shows_an_id_column(string $tab): void
    {
        $this->makeBranch('Alpha');

        $this->get("/reports?tab={$tab}")
            ->assertInertia(fn(Assert $page) => $page
                ->where('tab', $tab)
                ->where('columns', fn($columns) => ! collect($columns)->pluck('key')->contains('id')));
    }

    public function test_the_edits_rows_carry_everything_the_table_shows(): void
    {
        $branch = $this->makeBranch('Alpha');

        $this->get('/reports')
            ->assertInertia(fn(Assert $page) => $page
                ->where('rows.data.0.title', 'Alpha Edit')
                ->where('rows.data.0.client', 'Alpha Client')
                ->where('rows.data.0.company', 'Alpha Co')
                ->where('rows.data.0.project', 'Alpha Project')
                ->where('rows.data.0.priority', 'medium')
                ->where('rows.data.0.status', 'pending')
                ->where('rows.data.0.price', $this->money(500))
                ->where('rows.data.0.paid_at', null)
                ->where('rows.data.0._url', "/projects/{$branch['project']->id}"));
    }

    public function test_the_other_tabs_show_counts_and_money_per_row(): void
    {
        $project = Project::factory()->create();
        ChangeRequest::factory()->for($project)->status(ChangeRequestStatus::Done)->priced(1000)->paid()->create();
        ChangeRequest::factory()->for($project)->priced(500)->create();
        ChangeRequest::factory()->for($project)->status(ChangeRequestStatus::Cancelled)->priced(300)->create();

        // Edits are counted including the cancelled one, but money ignores cancelled edits.
        $this->get('/reports?tab=projects')
            ->assertInertia(fn(Assert $page) => $page
                ->where('rows.data.0.name', $project->name)
                ->where('rows.data.0.company', $project->company->name)
                ->where('rows.data.0.client', $project->company->client->name)
                ->where('rows.data.0.edits', 3)
                ->where('rows.data.0.value', $this->money(1500))
                ->where('rows.data.0.unpaid', $this->money(500)));

        $this->get('/reports?tab=companies')
            ->assertInertia(fn(Assert $page) => $page
                ->where('rows.data.0.projects', 1)
                ->where('rows.data.0.edits', 3)
                ->where('rows.data.0.value', $this->money(1500))
                ->where('rows.data.0.unpaid', $this->money(500)));

        $this->get('/reports?tab=clients')
            ->assertInertia(fn(Assert $page) => $page
                ->where('rows.data.0.companies', 1)
                ->where('rows.data.0.projects', 1));
    }

    public function test_the_edits_tab_shows_totals_and_the_others_do_not(): void
    {
        $project = Project::factory()->create();
        ChangeRequest::factory()->for($project)->status(ChangeRequestStatus::Done)->priced(1000)->paid()->create();
        ChangeRequest::factory()->for($project)->priced(500)->create();
        ChangeRequest::factory()->for($project)->status(ChangeRequestStatus::Cancelled)->priced(300)->create();

        $this->get('/reports')
            ->assertInertia(fn(Assert $page) => $page
                ->where('summary.value', $this->money(1500))
                ->where('summary.paid', $this->money(1000))
                ->where('summary.unpaid', $this->money(500)));

        $this->get('/reports?tab=clients')->assertInertia(fn(Assert $page) => $page->where('summary', null));
    }

    public function test_the_table_is_paginated_fifteen_per_page(): void
    {
        $project = Project::factory()->create();
        ChangeRequest::factory()->count(16)->for($project)->create();

        $this->get('/reports')->assertInertia(fn(Assert $page) => $page->has('rows.data', 15)->where('rows.total', 16));
        $this->get('/reports?page=2')->assertInertia(fn(Assert $page) => $page->has('rows.data', 1));
    }

    // ---- Filters ----

    public static function filterTypes(): array
    {
        return [
            'client' => ['clients', 'client'],
            'company' => ['companies', 'company'],
            'project' => ['projects', 'project'],
            'edit' => ['edits', 'edit'],
        ];
    }

    #[DataProvider('filterTypes')]
    public function test_each_filter_narrows_every_tab(string $filter, string $record): void
    {
        $alpha = $this->makeBranch('Alpha');
        $this->makeBranch('Beta');

        $counts = $this->counts([$filter => [$alpha[$record]->id]]);

        $this->assertSame(['edits' => 1, 'clients' => 1, 'companies' => 1, 'projects' => 1], $counts);
    }

    public function test_several_choices_in_one_filter_add_up(): void
    {
        $alpha = $this->makeBranch('Alpha');
        $beta = $this->makeBranch('Beta');
        $this->makeBranch('Gamma');

        $counts = $this->counts(['clients' => [$alpha['client']->id, $beta['client']->id]]);

        $this->assertSame(['edits' => 2, 'clients' => 2, 'companies' => 2, 'projects' => 2], $counts);
    }

    public function test_filters_that_do_not_belong_together_show_nothing(): void
    {
        $alpha = $this->makeBranch('Alpha');
        $beta = $this->makeBranch('Beta');

        $counts = $this->counts(['clients' => [$alpha['client']->id], 'projects' => [$beta['project']->id]]);

        $this->assertSame(['edits' => 0, 'clients' => 0, 'companies' => 0, 'projects' => 0], $counts);
    }

    public function test_the_chosen_filters_come_back_with_their_names_for_the_chips(): void
    {
        $alpha = $this->makeBranch('Alpha');

        $query = http_build_query([
            'clients' => [$alpha['client']->id],
            'companies' => [$alpha['company']->id],
            'projects' => [$alpha['project']->id],
            'edits' => [$alpha['edit']->id],
        ]);

        $this->get("/reports?{$query}")
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.clients', [$alpha['client']->id])
                ->where('selected.clients.0.title', 'Alpha Client')
                ->where('selected.companies.0.title', 'Alpha Co')
                ->where('selected.companies.0.subtitle', 'Alpha Client')
                ->where('selected.projects.0.subtitle', 'Alpha Client · Alpha Co')
                ->where('selected.edits.0.subtitle', 'Alpha Client · Alpha Co · Alpha Project'));
    }

    public function test_the_date_range_filters_by_created_date_and_includes_both_ends(): void
    {
        $project = Project::factory()->create();
        ChangeRequest::factory()->for($project)->create(['title' => 'January edit', 'created_at' => '2026-01-05 10:00:00']);
        ChangeRequest::factory()->for($project)->create(['title' => 'February edit', 'created_at' => '2026-02-10 23:30:00']);
        ChangeRequest::factory()->for($project)->create(['title' => 'March edit', 'created_at' => '2026-03-15 08:00:00']);

        // A range around February only.
        $this->get('/reports?from=2026-02-01&to=2026-02-28')
            ->assertInertia(fn(Assert $page) => $page
                ->where('rows.total', 1)
                ->where('rows.data.0.title', 'February edit')
                ->where('filters.from', '2026-02-01')
                ->where('filters.to', '2026-02-28'));

        // A single day, even late in the evening, is included.
        $this->get('/reports?from=2026-02-10&to=2026-02-10')
            ->assertInertia(fn(Assert $page) => $page->where('rows.total', 1)->where('rows.data.0.title', 'February edit'));

        // Only a start date, or only an end date.
        $this->get('/reports?from=2026-02-01')->assertInertia(fn(Assert $page) => $page->where('rows.total', 2));
        $this->get('/reports?to=2026-02-10')->assertInertia(fn(Assert $page) => $page->where('rows.total', 2));
    }

    public function test_the_date_range_also_applies_to_the_other_tabs(): void
    {
        $this->makeBranch('Alpha'); // everything was created just now

        $this->assertSame(
            ['edits' => 0, 'clients' => 0, 'companies' => 0, 'projects' => 0],
            $this->counts(['from' => now()->addDay()->toDateString()]),
        );
    }

    public function test_bad_input_is_rejected(): void
    {
        $this->get('/reports?from=2026-03-01&to=2026-02-01')->assertSessionHasErrors('to');
        $this->get('/reports?from=not-a-date')->assertSessionHasErrors('from');
        $this->get('/reports?clients[]=abc')->assertSessionHasErrors('clients.0');
        $this->get('/reports?tab=banana')->assertSessionHasErrors('tab');
    }

    // ---- Excel export ----

    public static function exportScopes(): array
    {
        return [
            'edits' => ['edits'],
            'clients' => ['clients'],
            'companies' => ['companies'],
            'projects' => ['projects'],
            'all tabs' => ['all'],
        ];
    }

    #[DataProvider('exportScopes')]
    public function test_every_export_downloads_an_excel_file(string $scope): void
    {
        $this->makeBranch('Alpha');

        $response = $this->get("/reports/export?tab={$scope}")->assertOk();

        $disposition = $response->headers->get('content-disposition');

        $this->assertStringContainsString("report_{$scope}_", $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);
    }

    public function test_exporting_with_no_data_still_works(): void
    {
        $this->get('/reports/export?tab=all')->assertOk();
    }

    public function test_the_export_rejects_an_unknown_tab(): void
    {
        $this->get('/reports/export?tab=banana')->assertSessionHasErrors('tab');
    }

    #[DataProvider('tabs')]
    public function test_the_export_contains_exactly_the_rows_shown_on_screen(string $tab): void
    {
        $alpha = $this->makeBranch('Alpha');
        $this->makeBranch('Beta');
        $params = ['clients' => [$alpha['client']->id]];

        // What the page shows for this tab and filter...
        $screen = [];

        $this->get('/reports?' . http_build_query(['tab' => $tab] + $params))
            ->assertInertia(function (Assert $page) use (&$screen) {
                $page->where('rows.data', function ($rows) use (&$screen) {
                    $screen = collect($rows)->all();

                    return true;
                });
            });

        // ...and what the Excel sheet is built from, with the same filter.
        $sheet = new ReportSheet($tab, ReportFilters::fromRequest(Request::create('/reports/export', 'GET', $params)));
        $exported = $sheet->query()->get();

        $this->assertCount(1, $screen);
        $this->assertCount(count($screen), $exported);

        // The first column (the name or the title) is the same text on screen and in the file.
        $key = Report::columns($tab)[0]['key'];

        $this->assertSame(
            collect($screen)->pluck($key)->sort()->values()->all(),
            $exported->map(fn($model) => $sheet->map($model)[0])->sort()->values()->all(),
        );
    }
}
