<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
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
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('clients', 'phone')->ignore($client)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client)],
            'last_follow_up' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[\s\-().]/', '', (string) $this->input('phone')),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'A client with this phone number already exists (it may have been deleted).',
            'email.unique' => 'A client with this email already exists (it may have been deleted).',
        ];
    }
}
