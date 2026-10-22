<?php

namespace App\Exports;

use App\Reports\Report;
use App\Reports\ReportFilters;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportWorkbook implements Export, WithMultipleSheets
{
    public function __construct(private readonly ReportFilters $filters) {}

    public function sheets(): array
    {
        return collect(array_keys(Report::TABS))
            ->map(fn(string $tab) => new ReportSheet($tab, $this->filters))
            ->all();
    }
}
