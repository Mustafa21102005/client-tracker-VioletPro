<?php

namespace Tests\Unit;

use App\Models\ChangeRequest;
use App\Models\Project;
use App\Reports\ReportFilters;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportFiltersTest extends TestCase
{
    public function test_ids_are_cleaned_up_and_empty_dates_become_null(): void
    {
        $filters = ReportFilters::fromRequest(Request::create('/', 'GET', [
            'clients' => ['3', 'abc', '3', '7'],
            'from' => '2026-01-01',
            'to' => '',
        ]));

        $this->assertSame([3, 7], $filters->clients);
        $this->assertSame([], $filters->companies);
        $this->assertSame('2026-01-01', $filters->from);
        $this->assertNull($filters->to);
    }

    public function test_the_filters_can_be_turned_back_into_plain_data(): void
    {
        $filters = new ReportFilters(clients: [1], edits: [9], from: '2026-01-01');

        $this->assertSame([
            'clients' => [1],
            'companies' => [],
            'projects' => [],
            'edits' => [9],
            'from' => '2026-01-01',
            'to' => null,
        ], $filters->toArray());
    }

    public function test_the_date_range_includes_the_first_and_last_day(): void
    {
        $project = Project::factory()->create();
        ChangeRequest::factory()->for($project)->create(['created_at' => '2026-02-01 00:00:00']);
        ChangeRequest::factory()->for($project)->create(['created_at' => '2026-02-28 23:59:59']);
        ChangeRequest::factory()->for($project)->create(['created_at' => '2026-03-01 00:00:01']);

        $query = ChangeRequest::query();
        (new ReportFilters(from: '2026-02-01', to: '2026-02-28'))->applyDates($query);

        $this->assertSame(2, $query->count());
    }
}
