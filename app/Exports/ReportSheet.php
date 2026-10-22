<?php

namespace App\Exports;

use App\Reports\Report;
use App\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportSheet implements FromQuery, WithColumnWidths, WithColumnFormatting, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private readonly array $columns;

    public function __construct(
        private readonly string $tab,
        private readonly ReportFilters $filters,
    ) {
        $this->columns = Report::columns($tab);
    }

    // The very same query the page uses, so the file always matches the screen.
    public function query(): Builder
    {
        return Report::query($this->tab, $this->filters);
    }

    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }

    /** @param Model $row */
    public function map($row): array
    {
        $values = Report::row($this->tab, $row);

        return collect($this->columns)
            ->map(function (array $column) use ($values) {
                $value = $values[$column['key']] ?? null;

                // "urgent" becomes "Urgent", "done" becomes "Done".
                return in_array($column['type'], ['priority', 'status'], true) && is_string($value) ? ucfirst($value) : $value;
            })
            ->all();
    }

    public function title(): string
    {
        return Report::TABS[$this->tab];
    }

    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->columns as $index => $column) {
            if ($column['type'] === 'money') {
                $formats[Coordinate::stringFromColumnIndex($index + 1)] = '#,##0.00';
            }
        }

        return $formats;
    }

    public function columnWidths(): array
    {
        $widths = [];

        foreach ($this->columns as $index => $column) {
            $widths[Coordinate::stringFromColumnIndex($index + 1)] = match ($column['type']) {
                'number' => 10,
                'date' => 14,
                'priority', 'status' => 12,
                'money' => 16,
                default => $column['link'] ? 34 : 26,
            };
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
