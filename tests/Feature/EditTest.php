<?php

namespace Tests\Feature;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Add dark mode',
            'description' => 'Let users switch themes.',
            'priority' => 'high',
            'status' => 'pending',
            'price' => '500',
            'paid_at' => null,
        ], $overrides);
    }

    // ---- Create ----

    public function test_an_edit_can_be_added_to_a_project(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/change-requests", $this->validData())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('change_requests', [
            'project_id' => $project->id,
            'title' => 'Add dark mode',
            'priority' => 'high',
            'status' => 'pending',
        ]);
    }

    public function test_the_title_is_required(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/change-requests", $this->validData(['title' => '']))
            ->assertSessionHasErrors('title');
    }

    public function test_priority_and_status_must_be_known_values(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/change-requests", $this->validData(['priority' => 'banana', 'status' => 'whenever']))
            ->assertSessionHasErrors(['priority', 'status']);

        $this->assertDatabaseCount('change_requests', 0);
    }

    public function test_the_price_must_be_a_positive_number(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/change-requests", $this->validData(['price' => 'abc']))->assertSessionHasErrors('price');
        $this->post("/projects/{$project->id}/change-requests", $this->validData(['price' => '-5']))->assertSessionHasErrors('price');
    }

    public function test_the_price_is_optional(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/change-requests", $this->validData(['price' => null]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_paid_date_needs_a_price(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/change-requests", $this->validData(['price' => null, 'paid_at' => now()->toDateString()]))
            ->assertSessionHasErrors(['paid_at' => 'Add a price before marking this request as paid.']);

        $this->post("/projects/{$project->id}/change-requests", $this->validData(['price' => '500', 'paid_at' => now()->toDateString()]))
            ->assertSessionHasNoErrors();
    }

    public function test_the_paid_date_cannot_be_in_the_future(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/change-requests", $this->validData(['paid_at' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('paid_at');
    }

    public function test_an_edit_cannot_be_added_to_a_project_that_does_not_exist(): void
    {
        $this->post('/projects/999999/change-requests', $this->validData())->assertNotFound();
    }

    // ---- Update and delete ----

    public function test_an_edit_can_be_updated(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->put("/change-requests/{$edit->id}", $this->validData(['title' => 'New title', 'status' => 'done', 'priority' => 'urgent']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $edit->refresh();

        $this->assertSame('New title', $edit->title);
        $this->assertSame(ChangeRequestStatus::Done, $edit->status);
        $this->assertSame(Priority::Urgent, $edit->priority);
    }

    public function test_an_edit_is_soft_deleted(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->delete("/change-requests/{$edit->id}")->assertSessionHas('success');

        $this->assertSoftDeleted($edit);
    }

    // ---- One-click actions ----

    public function test_an_edit_can_be_marked_as_done(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/done")->assertSessionHas('success');

        $this->assertSame(ChangeRequestStatus::Done, $edit->refresh()->status);
    }

    public function test_an_edit_with_a_price_can_be_marked_as_paid(): void
    {
        $edit = ChangeRequest::factory()->priced(500)->create();

        $this->post("/change-requests/{$edit->id}/paid")->assertSessionHas('success');

        $this->assertNotNull($edit->refresh()->paid_at);
    }

    public function test_an_edit_without_a_price_cannot_be_marked_as_paid(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->post("/change-requests/{$edit->id}/paid")->assertSessionHas('error');

        $this->assertNull($edit->refresh()->paid_at);
    }

    // ---- The All Edits page ----

    public function test_the_page_lists_edits_with_their_client_company_and_project(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->get('/edits')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('change-requests/Index')
                ->where('edits.total', 1)
                ->where('edits.data.0.title', $edit->title)
                ->where('edits.data.0.project.name', $edit->project->name)
                ->where('edits.data.0.project.company.name', $edit->project->company->name)
                ->where('edits.data.0.project.company.client.name', $edit->project->company->client->name));
    }

    // Four edits that most filter tests below rely on.
    private function seedMixedEdits(): void
    {
        ChangeRequest::factory()->status(ChangeRequestStatus::Done)->priced(1000)->paid()->create(['title' => 'Paid job']);
        ChangeRequest::factory()->status(ChangeRequestStatus::Done)->priced(500)->create(['title' => 'Unpaid job']);
        ChangeRequest::factory()->create(['title' => 'Free fix']); // pending, no price
        ChangeRequest::factory()->status(ChangeRequestStatus::Cancelled)->priced(300)->create(['title' => 'Cancelled job']);
    }

    public function test_it_filters_by_status_and_ignores_unknown_values(): void
    {
        $this->seedMixedEdits();

        $this->get('/edits?status=done')
            ->assertInertia(fn(Assert $page) => $page->where('edits.total', 2)->where('filters.status', 'done'));

        $this->get('/edits?status=banana')
            ->assertInertia(fn(Assert $page) => $page->where('edits.total', 4)->where('filters.status', null));
    }

    public function test_it_filters_by_priority(): void
    {
        $this->seedMixedEdits();
        ChangeRequest::factory()->priority(Priority::Urgent)->create(['title' => 'Urgent one']);

        $this->get('/edits?priority=urgent')
            ->assertInertia(fn(Assert $page) => $page->where('edits.total', 1)->where('edits.data.0.title', 'Urgent one'));
    }

    public function test_it_filters_by_payment(): void
    {
        $this->seedMixedEdits();

        // Paid: has a payment date.
        $this->get('/edits?payment=paid')
            ->assertInertia(fn(Assert $page) => $page->where('edits.total', 1)->where('edits.data.0.title', 'Paid job'));

        // Unpaid: has a price, no payment date, and is not cancelled (so "Cancelled job" is left out).
        $this->get('/edits?payment=unpaid')
            ->assertInertia(fn(Assert $page) => $page->where('edits.total', 1)->where('edits.data.0.title', 'Unpaid job'));

        // No price at all.
        $this->get('/edits?payment=free')
            ->assertInertia(fn(Assert $page) => $page->where('edits.total', 1)->where('edits.data.0.title', 'Free fix'));
    }

    public function test_search_looks_at_the_edit_project_company_and_client(): void
    {
        $client = Client::factory()->create(['name' => 'Qwerty Zyxel']);
        $company = Company::factory()->for($client)->create(['name' => 'Zyxco Holdings']);
        $project = Project::factory()->for($company)->create(['name' => 'Zyxpro Portal']);
        ChangeRequest::factory()->for($project)->create(['title' => 'Zyxfix login']);

        ChangeRequest::factory()->count(2)->create(); // noise that must not match

        foreach (['Zyxfix', 'Zyxpro', 'Zyxco', 'Qwerty'] as $term) {
            $this->get("/edits?search={$term}")
                ->assertInertia(fn(Assert $page) => $page
                    ->where('edits.total', 1)
                    ->where('edits.data.0.title', 'Zyxfix login'));
        }
    }

    public function test_it_sorts_by_price_with_unpriced_edits_last(): void
    {
        ChangeRequest::factory()->priced(100)->create();
        ChangeRequest::factory()->priced(900)->create();
        ChangeRequest::factory()->priced(500)->create();
        ChangeRequest::factory()->create(); // no price

        $this->get('/edits?sort=price_desc')
            ->assertInertia(fn(Assert $page) => $page
                ->where('edits.data.0.price', '900.00')
                ->where('edits.data.1.price', '500.00')
                ->where('edits.data.2.price', '100.00')
                ->where('edits.data.3.price', null));

        $this->get('/edits?sort=price_asc')
            ->assertInertia(fn(Assert $page) => $page
                ->where('edits.data.0.price', '100.00')
                ->where('edits.data.1.price', '500.00')
                ->where('edits.data.2.price', '900.00')
                ->where('edits.data.3.price', null));
    }

    public function test_the_totals_cover_the_filtered_set_and_ignore_cancelled_edits(): void
    {
        $this->seedMixedEdits();

        // Everything: 1000 paid + 500 unpaid (the cancelled 300 is excluded).
        $this->get('/edits')
            ->assertInertia(fn(Assert $page) => $page
                ->where('summary.value', fn($value) => (float) $value === 1500.0)
                ->where('summary.unpaid', fn($value) => (float) $value === 500.0));

        // Narrowed to paid work only.
        $this->get('/edits?payment=paid')
            ->assertInertia(fn(Assert $page) => $page
                ->where('summary.value', fn($value) => (float) $value === 1000.0)
                ->where('summary.unpaid', fn($value) => (float) $value === 0.0));
    }

    public function test_the_page_is_paginated_fifteen_per_page(): void
    {
        $project = Project::factory()->create();
        ChangeRequest::factory()->count(16)->for($project)->create();

        $this->get('/edits')
            ->assertInertia(fn(Assert $page) => $page->has('edits.data', 15)->where('edits.total', 16));

        $this->get('/edits?page=2')
            ->assertInertia(fn(Assert $page) => $page->has('edits.data', 1));
    }

    public function test_the_edit_search_endpoint_returns_matches_with_their_context(): void
    {
        $client = Client::factory()->create(['name' => 'Layla Mansour']);
        $company = Company::factory()->for($client)->create(['name' => 'Zeta Retail']);
        $project = Project::factory()->for($company)->create(['name' => 'Zeta Shop']);
        ChangeRequest::factory()->for($project)->create(['title' => 'Add wishlist']);
        ChangeRequest::factory()->create(['title' => 'Unrelated edit']);

        $this->getJson('/edits/search?q=wishlist')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Add wishlist')
            ->assertJsonPath('0.subtitle', 'Layla Mansour · Zeta Retail · Zeta Shop');
    }
}
