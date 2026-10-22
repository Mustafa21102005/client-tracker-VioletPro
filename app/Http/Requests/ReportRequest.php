<?php

namespace App\Http\Requests;

use App\Reports\Report;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in([...array_keys(Report::TABS), 'all'])],
            'clients' => ['nullable', 'array', 'max:100'],
            'clients.*' => ['integer'],
            'companies' => ['nullable', 'array', 'max:100'],
            'companies.*' => ['integer'],
            'projects' => ['nullable', 'array', 'max:100'],
            'projects.*' => ['integer'],
            'edits' => ['nullable', 'array', 'max:100'],
            'edits.*' => ['integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
        ];
    }

    /** The tab to show on screen ("all" only makes sense for the export, so it falls back to edits). */
    public function tab(): string
    {
        $tab = $this->input('tab');

        return in_array($tab, array_keys(Report::TABS), true) ? $tab : 'edits';
    }
}
