<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class AttachmentFileSize implements ValidationRule
{
    public const IMAGE_MAX_KB = 1024;     // 1 MB
    public const DOCUMENT_MAX_KB = 2048;  // 2 MB (PDF and Word)

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $isImage = str_starts_with((string) $value->getMimeType(), 'image/');
        $maxKb = $isImage ? self::IMAGE_MAX_KB : self::DOCUMENT_MAX_KB;

        if ($value->getSize() > $maxKb * 1024) {
            $fail($isImage
                ? sprintf('Images can be at most %d MB.', self::IMAGE_MAX_KB / 1024)
                : sprintf('PDF and Word files can be at most %d MB.', self::DOCUMENT_MAX_KB / 1024));
        }
    }
}
