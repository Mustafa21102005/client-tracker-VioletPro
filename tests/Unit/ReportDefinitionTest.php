<?php

namespace Tests\Unit;

use App\Models\ChangeRequest;
use App\Reports\Report;
use App\Reports\ReportFilters;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportDefinitionTest extends TestCase
{
    public static function tabs(): array
    {
        return [
            'edits' => ['edits'],
            'clients' => ['clients'],
            'companies' => ['companies'],
            'projects' => ['projects'],
        ];
    }

    public function test_the_report_has_these_four_tabs(): void
    {
        $this->assertSame(['edits', 'clients', 'companies', 'projects'], array_keys(Report::TABS));
    }

    #[DataProvider('tabs')]
    public function test_columns_never_include_the_id_and_every_column_has_a_value_in_each_row(string $tab): void
    {
        ChangeRequest::factory()->priced(100)->create(); // one full chain, so every tab has a row

        $columns = Report::columns($tab);
        $keys = array_column($columns, 'key');

        $this->assertNotContains('id', $keys);

        $row = Report::row($tab, Report::query($tab, new ReportFilters)->firstOrFail());

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $row, "The '{$key}' column has no value in the {$tab} rows.");
        }

        foreach ($columns as $column) {
            $this->assertContains($column['type'], ['text', 'number', 'money', 'date', 'priority', 'status']);
        }
    }
}
