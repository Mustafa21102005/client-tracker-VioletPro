<?php

namespace App\Http\Requests;

use App\Rules\AttachmentFileSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttachmentRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:1000'],
            'file' => ['bail', 'nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,gif', new AttachmentFileSize],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Only PDF, Word and image files (JPG, PNG, WEBP, GIF) are allowed.',
        ];
    }
}
