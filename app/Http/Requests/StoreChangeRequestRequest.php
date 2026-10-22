<?php

namespace App\Http\Requests;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'status' => ['required', Rule::enum(ChangeRequestStatus::class)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
                Rule::prohibitedIf(fn() => blank($this->input('price'))),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.prohibited' => 'Add a price before marking this request as paid.',
        ];
    }
}
