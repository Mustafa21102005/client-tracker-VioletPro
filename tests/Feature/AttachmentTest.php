<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\ChangeRequest;
use App\Models\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Uploaded files go to a temporary fake disk, never to your real storage folder.
        Storage::fake('public');

        $this->signIn();
    }

    // ---- Helpers ----

    private function png(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 200, 200);
    }

    // A tiny file that starts like a real PDF, so the server detects it as one.
    private function pdf(string $name = 'contract.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    }

    private function makeAttachment(UploadedFile $file, ?string $note = null, ?ChangeRequest $edit = null): Attachment
    {
        $attachment = Attachment::factory()
            ->for($edit ?? ChangeRequest::factory()->create(), 'changeRequest')
            ->create(['note' => $note]);

        $attachment->addMedia($file)->toMediaCollection('files');

        return $attachment;
    }

    // ---- Create ----

    public function test_an_image_can_be_attached_to_an_edit(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => $this->png(), 'note' => 'Screenshot of the bug'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $attachment = Attachment::firstOrFail();
        $media = $attachment->getFirstMedia('files');

        $this->assertSame($edit->id, $attachment->change_request_id);
        $this->assertSame('Screenshot of the bug', $attachment->note);
        $this->assertSame('photo.png', $media->file_name);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_a_pdf_can_be_attached_to_an_edit(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $this->assertSame('application/pdf', Attachment::firstOrFail()->getFirstMedia('files')->mime_type);
    }

    public function test_a_file_is_required(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/attachments", ['note' => 'No file here'])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_other_file_types_are_rejected(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
            ->assertSessionHasErrors(['file' => 'Only PDF, Word and image files (JPG, PNG, WEBP, GIF) are allowed.']);

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_images_can_be_at_most_1_mb(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => $this->png()->size(1025)])
            ->assertSessionHasErrors(['file' => 'Images can be at most 1 MB.']);

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => $this->png()->size(1024)])
            ->assertSessionHasNoErrors();
    }

    public function test_pdf_and_word_files_can_be_at_most_2_mb(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => $this->pdf()->size(2049)])
            ->assertSessionHasErrors(['file' => 'PDF and Word files can be at most 2 MB.']);

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => $this->pdf()->size(2048)])
            ->assertSessionHasNoErrors();
    }

    public function test_the_note_is_limited_to_1000_characters(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/attachments", ['file' => $this->png(), 'note' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('note');
    }

    public function test_a_file_cannot_be_attached_to_an_edit_that_does_not_exist(): void
    {
        $this->post('/change-requests/999999/attachments', ['file' => $this->png()])->assertNotFound();
    }

    // ---- Update ----

    public function test_only_the_note_can_be_changed(): void
    {
        $attachment = $this->makeAttachment($this->png(), 'Old note');

        $this->put("/attachments/{$attachment->id}", ['note' => 'New note'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('New note', $attachment->refresh()->note);
        $this->assertSame('photo.png', $attachment->getFirstMedia('files')->file_name);
    }

    public function test_the_file_can_be_replaced_and_the_old_one_is_removed(): void
    {
        $attachment = $this->makeAttachment($this->png('old.png'), 'A note');
        $oldPath = $attachment->getFirstMedia('files')->getPathRelativeToRoot();

        // A browser sends an edit as a POST that carries "_method: put".
        $this->post("/attachments/{$attachment->id}", ['_method' => 'put', 'note' => 'A note', 'file' => $this->pdf('new.pdf')])
            ->assertSessionHasNoErrors();

        $attachment->refresh();

        $this->assertCount(1, $attachment->getMedia('files'));
        $this->assertSame('new.pdf', $attachment->getFirstMedia('files')->file_name);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_a_replacement_file_must_follow_the_same_rules(): void
    {
        $attachment = $this->makeAttachment($this->png());

        $this->post("/attachments/{$attachment->id}", ['_method' => 'put', 'file' => $this->png('big.png')->size(2000)])
            ->assertSessionHasErrors(['file' => 'Images can be at most 1 MB.']);
    }

    // ---- Delete ----

    public function test_deleting_an_attachment_removes_its_file_from_disk(): void
    {
        $attachment = $this->makeAttachment($this->png());
        $path = $attachment->getFirstMedia('files')->getPathRelativeToRoot();

        $this->delete("/attachments/{$attachment->id}")->assertSessionHas('success');

        $this->assertModelMissing($attachment);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_soft_deleting_an_edit_keeps_its_attachments(): void
    {
        $edit = ChangeRequest::factory()->create();
        $attachment = $this->makeAttachment($this->png(), null, $edit);

        $edit->delete();

        $this->assertModelExists($attachment);
        Storage::disk('public')->assertExists($attachment->getFirstMedia('files')->getPathRelativeToRoot());
    }

    public function test_permanently_deleting_an_edit_removes_its_attachments_and_files(): void
    {
        $edit = ChangeRequest::factory()->create();
        $attachment = $this->makeAttachment($this->png(), null, $edit);
        $path = $attachment->getFirstMedia('files')->getPathRelativeToRoot();

        $edit->forceDelete();

        $this->assertModelMissing($attachment);
        Storage::disk('public')->assertMissing($path);
    }

    // ---- Opening files (they are private: login required) ----

    public function test_a_logged_in_user_can_open_an_image_in_the_browser(): void
    {
        $attachment = $this->makeAttachment($this->png());

        $this->get("/attachments/{$attachment->id}/file")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename=photo.png');
    }

    public function test_a_pdf_also_opens_inline(): void
    {
        $attachment = $this->makeAttachment($this->pdf());

        $this->get("/attachments/{$attachment->id}/file")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_visitor_who_is_not_logged_in_cannot_open_a_file(): void
    {
        $attachment = $this->makeAttachment($this->png());

        Auth::forgetGuards(); // log the test user out

        $this->get("/attachments/{$attachment->id}/file")->assertRedirect(route('login'));
    }

    public function test_an_attachment_without_a_file_returns_not_found(): void
    {
        $attachment = Attachment::factory()->create(); // no media

        $this->get("/attachments/{$attachment->id}/file")->assertNotFound();
    }

    // ---- The All Attachments page ----

    public function test_the_page_lists_files_with_details_and_their_edit(): void
    {
        $edit = ChangeRequest::factory()->create();
        $this->makeAttachment($this->png('photo.png'), 'Hero image', $edit);

        $this->get('/attachments')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('attachments/Index')
                ->where('attachments.total', 1)
                ->where('attachments.data.0.note', 'Hero image')
                ->where('attachments.data.0.file.name', 'photo.png')
                ->where('attachments.data.0.file.mime_type', 'image/png')
                ->where('attachments.data.0.change_request.title', $edit->title)
                ->where('attachments.data.0.change_request.project.company.client.name', $edit->project->company->client->name));
    }

    public function test_it_filters_by_file_type_and_the_tab_counts_ignore_the_filter(): void
    {
        $this->makeAttachment($this->png('photo.png'));
        $this->makeAttachment($this->pdf('one.pdf'));
        $this->makeAttachment($this->pdf('two.pdf'));

        $this->get('/attachments?type=pdf')
            ->assertInertia(fn(Assert $page) => $page
                ->where('attachments.total', 2)
                ->where('filters.type', 'pdf')
                ->where('typeCounts.all', 3)
                ->where('typeCounts.pdf', 2)
                ->where('typeCounts.image', 1)
                ->where('typeCounts.word', 0));

        $this->get('/attachments?type=image')->assertInertia(fn(Assert $page) => $page->where('attachments.total', 1));
        $this->get('/attachments?type=word')->assertInertia(fn(Assert $page) => $page->where('attachments.total', 0));

        // An unknown type is ignored.
        $this->get('/attachments?type=banana')
            ->assertInertia(fn(Assert $page) => $page->where('attachments.total', 3)->where('filters.type', null));
    }

    public function test_search_looks_at_notes_file_names_edits_and_clients(): void
    {
        $client = Client::factory()->create(['name' => 'Qwerty Zyxel']);
        $edit = ChangeRequest::factory()->create(['title' => 'Zyxfix login']);
        $edit->project->company->update(['client_id' => $client->id]);

        $this->makeAttachment($this->png('zyxfile.png'), 'Zyxnote here', $edit);
        $this->makeAttachment($this->pdf('other.pdf'), 'Nothing to see'); // noise

        foreach (['Zyxnote', 'zyxfile', 'Zyxfix', 'Qwerty'] as $term) {
            $this->get("/attachments?search={$term}")
                ->assertInertia(fn(Assert $page) => $page
                    ->where('attachments.total', 1)
                    ->where('attachments.data.0.file.name', 'zyxfile.png'));
        }
    }

    public function test_it_sorts_by_name_and_by_size(): void
    {
        $this->makeAttachment($this->pdf('middle.pdf'));
        $this->makeAttachment($this->png('zeta.png')); // the largest of the three
        $this->makeAttachment($this->pdf('alpha.pdf'));

        $this->get('/attachments?sort=name')
            ->assertInertia(fn(Assert $page) => $page
                ->where('attachments.data.0.file.name', 'alpha.pdf')
                ->where('attachments.data.2.file.name', 'zeta.png'));

        $this->get('/attachments?sort=size')
            ->assertInertia(fn(Assert $page) => $page->where('attachments.data.0.file.name', 'zeta.png'));
    }

    public function test_the_header_shows_the_total_size_of_the_files(): void
    {
        $this->makeAttachment($this->png('one.png'));
        $this->makeAttachment($this->pdf('two.pdf'));

        $expected = (int) Media::query()->sum('size');

        $this->get('/attachments')->assertInertia(fn(Assert $page) => $page->where('summary.size', $expected));
    }

    public function test_the_page_is_paginated_twelve_per_page(): void
    {
        Attachment::factory()->count(13)->create();

        $this->get('/attachments')
            ->assertInertia(fn(Assert $page) => $page->has('attachments.data', 12)->where('attachments.total', 13));
    }

    public function test_attachments_are_included_on_the_edits_page(): void
    {
        $edit = ChangeRequest::factory()->create();
        $this->makeAttachment($this->png('proof.png'), null, $edit);

        $this->get('/edits')
            ->assertInertia(fn(Assert $page) => $page
                ->has('edits.data.0.attachments', 1)
                ->where('edits.data.0.attachments.0.file.name', 'proof.png'));
    }
}
