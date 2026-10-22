<?php

namespace Tests\Unit;

use App\Rules\AttachmentFileSize;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttachmentFileSizeTest extends TestCase
{
    // Runs the rule and returns the error messages it produced (an empty list means "passed").
    private function failures(mixed $value): array
    {
        $messages = [];

        (new AttachmentFileSize)->validate('file', $value, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }

    public function test_the_limits_are_1_mb_for_images_and_2_mb_for_documents(): void
    {
        $this->assertSame(1024, AttachmentFileSize::IMAGE_MAX_KB);
        $this->assertSame(2048, AttachmentFileSize::DOCUMENT_MAX_KB);
    }

    public function test_images_at_the_limit_pass_and_larger_ones_fail(): void
    {
        $this->assertSame([], $this->failures(UploadedFile::fake()->create('a.png', 1024, 'image/png')));
        $this->assertSame(['Images can be at most 1 MB.'], $this->failures(UploadedFile::fake()->create('a.png', 1025, 'image/png')));
    }

    public function test_pdf_files_at_the_limit_pass_and_larger_ones_fail(): void
    {
        $this->assertSame([], $this->failures(UploadedFile::fake()->create('a.pdf', 2048, 'application/pdf')));
        $this->assertSame(['PDF and Word files can be at most 2 MB.'], $this->failures(UploadedFile::fake()->create('a.pdf', 2049, 'application/pdf')));
    }

    public function test_word_files_use_the_document_limit(): void
    {
        $word = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $this->assertSame([], $this->failures(UploadedFile::fake()->create('a.docx', 2048, $word)));
        $this->assertSame(['PDF and Word files can be at most 2 MB.'], $this->failures(UploadedFile::fake()->create('a.docx', 2049, $word)));
    }

    public function test_something_that_is_not_a_file_is_left_to_the_other_rules(): void
    {
        $this->assertSame([], $this->failures('not a file'));
        $this->assertSame([], $this->failures(null));
    }
}
