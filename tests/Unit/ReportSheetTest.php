<?php

namespace Tests\Unit;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use App\Exports\ReportSheet;
use App\Exports\ReportWorkbook;
use App\Models\ChangeRequest;
use App\Reports\ReportFilters;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportSheetTest extends TestCase
{
    public function test_the_edits_sheet_has_the_right_title_and_headings(): void
    {
        $sheet = new ReportSheet('edits', new ReportFilters);

        $this->assertSame('Edits', $sheet->title());
        $this->assertSame(
            ['Edit', 'Client', 'Company', 'Project', 'Priority', 'Status', 'Price (USD)', 'Paid on', 'Created'],
            $sheet->headings(),
        );
    }

    public function test_an_edit_becomes_a_row_with_readable_words_and_a_real_number(): void
    {
        $edit = ChangeRequest::factory()
            ->priority(Priority::Urgent)
            ->status(ChangeRequestStatus::Done)
            ->priced(750.5)
            ->paid(Carbon::parse('2026-03-01'))
            ->create(['title' => 'Fix checkout']);

        $row = (new ReportSheet('edits', new ReportFilters))->map($edit);

        $this->assertSame([
            'Fix checkout',
            $edit->project->company->client->name,
            $edit->project->company->name,
            $edit->project->name,
            'Urgent',  // not "urgent"
            'Done',    // not "done"
            750.5,     // a number, so Excel can add it up
            '2026-03-01',
            $edit->created_at->format('Y-m-d'),
        ], $row);
    }

    public function test_the_money_columns_get_a_number_format(): void
    {
        // Price is the 7th column (G) on the edits sheet.
        $this->assertSame(['G' => '#,##0.00'], (new ReportSheet('edits', new ReportFilters))->columnFormats());

        // Value and Unpaid are the 5th and 6th columns (E and F) on the companies sheet.
        $this->assertSame(['E' => '#,##0.00', 'F' => '#,##0.00'], (new ReportSheet('companies', new ReportFilters))->columnFormats());

        // The clients sheet has no money.
        $this->assertSame([], (new ReportSheet('clients', new ReportFilters))->columnFormats());
    }

    public function test_the_all_tabs_file_has_four_sheets_in_order(): void
    {
        $sheets = (new ReportWorkbook(new ReportFilters))->sheets();

        $this->assertCount(4, $sheets);
        $this->assertSame(['Edits', 'Clients', 'Companies', 'Projects'], array_map(fn(ReportSheet $sheet) => $sheet->title(), $sheets));
    }
}
