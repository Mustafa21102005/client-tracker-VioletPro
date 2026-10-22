<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Layla Mansour',
            'phone' => '0501234567',
            'email' => 'layla@example.com',
            'last_follow_up' => null,
        ], $overrides);
    }

    // ---- List ----

    public function test_the_list_shows_clients_with_company_and_project_counts(): void
    {
        $client = Client::factory()->create();
        $company = Company::factory()->for($client)->create();
        Company::factory()->for($client)->create();
        Project::factory()->count(3)->for($company)->create();

        $this->get('/clients')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('clients/Index')
                ->where('clients.total', 1)
                ->where('clients.data.0.companies_count', 2)
                ->where('clients.data.0.projects_count', 3));
    }

    public function test_the_list_is_paginated_ten_per_page(): void
    {
        Client::factory()->count(12)->create();

        $this->get('/clients')
            ->assertInertia(fn(Assert $page) => $page->has('clients.data', 10)->where('clients.total', 12));

        $this->get('/clients?page=2')
            ->assertInertia(fn(Assert $page) => $page->has('clients.data', 2));
    }

    public function test_search_finds_clients_by_name_phone_or_email(): void
    {
        Client::factory()->create(['name' => 'Abdullah Harbi', 'phone' => '0501111111', 'email' => 'abdullah@example.com']);
        Client::factory()->create(['name' => 'Sara Qahtani', 'phone' => '0502222222', 'email' => 'sara@example.com']);

        foreach (['Harbi' => 'Abdullah Harbi', '0502222' => 'Sara Qahtani', 'sara@' => 'Sara Qahtani'] as $term => $expectedName) {
            $this->get('/clients?search=' . $term)
                ->assertInertia(fn(Assert $page) => $page
                    ->where('clients.total', 1)
                    ->where('clients.data.0.name', $expectedName)
                    ->where('filters.search', $term));
        }
    }

    // ---- Create ----

    public function test_a_client_can_be_created(): void
    {
        $this->post('/clients', $this->validData())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clients', ['name' => 'Layla Mansour', 'phone' => '0501234567']);
    }

    public function test_a_client_needs_a_name_and_a_phone(): void
    {
        $this->post('/clients', $this->validData(['name' => '', 'phone' => '']))
            ->assertSessionHasErrors(['name', 'phone']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_the_phone_is_limited_to_20_characters(): void
    {
        $this->post('/clients', $this->validData(['phone' => str_repeat('1', 21)]))
            ->assertSessionHasErrors('phone');
    }

    public function test_the_email_must_be_valid(): void
    {
        $this->post('/clients', $this->validData(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_the_last_follow_up_cannot_be_in_the_future(): void
    {
        $this->post('/clients', $this->validData(['last_follow_up' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('last_follow_up');
    }

    public function test_duplicate_phone_and_email_are_rejected(): void
    {
        Client::factory()->create(['phone' => '0509999999', 'email' => 'taken@example.com']);

        $this->post('/clients', $this->validData(['phone' => '0509999999', 'email' => 'taken@example.com']))
            ->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_the_email_is_optional_and_several_clients_can_have_none(): void
    {
        $this->post('/clients', $this->validData(['email' => null, 'phone' => '0501111111']))->assertSessionHasNoErrors();
        $this->post('/clients', $this->validData(['email' => null, 'phone' => '0502222222']))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('clients', 2);
    }

    // ---- Update ----

    public function test_a_client_can_be_updated_without_clashing_with_itself(): void
    {
        $client = Client::factory()->create(['phone' => '0503333333', 'email' => 'me@example.com']);

        $this->put("/clients/{$client->id}", $this->validData(['name' => 'New Name', 'phone' => '0503333333', 'email' => 'me@example.com']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'New Name']);
    }

    public function test_a_client_cannot_take_another_clients_phone(): void
    {
        Client::factory()->create(['phone' => '0504444444']);
        $client = Client::factory()->create(['phone' => '0505555555']);

        $this->put("/clients/{$client->id}", $this->validData(['phone' => '0504444444']))
            ->assertSessionHasErrors('phone');
    }

    // ---- Delete ----

    public function test_a_client_without_companies_is_soft_deleted(): void
    {
        $client = Client::factory()->create();

        $this->delete("/clients/{$client->id}")->assertSessionHas('success');

        $this->assertSoftDeleted($client);
    }

    public function test_a_client_with_companies_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        Company::factory()->for($client)->create();

        $this->delete("/clients/{$client->id}")->assertSessionHas('error');

        $this->assertNotSoftDeleted($client);
    }

    // ---- Detail page ----

    public function test_the_client_page_shows_their_companies_and_totals(): void
    {
        $client = Client::factory()->create();
        $company = Company::factory()->for($client)->create();
        $project = Project::factory()->for($company)->create();
        ChangeRequest::factory()->count(2)->for($project)->create();

        // Another client's data must not leak into this page.
        ChangeRequest::factory()->create();

        $this->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('clients/Show')
                ->where('client.id', $client->id)
                ->where('client.companies_count', 1)
                ->where('client.projects_count', 1)
                ->where('changeRequestsCount', 2)
                ->has('companies.data', 1));
    }

    // ---- Quick action and search ----

    public function test_a_client_can_be_marked_as_followed_up_today(): void
    {
        $client = Client::factory()->create(['last_follow_up' => now()->subMonths(2)->toDateString()]);

        $this->post("/clients/{$client->id}/follow-up")->assertSessionHas('success');

        $this->assertTrue($client->refresh()->last_follow_up->isToday());
    }

    public function test_the_client_search_endpoint_returns_matches_for_the_dropdown(): void
    {
        Client::factory()->create(['name' => 'Omar Haddad', 'phone' => '0506666666']);
        Client::factory()->create(['name' => 'Priya Nair']);

        $this->getJson('/clients/search?q=Haddad')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Omar Haddad')
            ->assertJsonPath('0.subtitle', '0506666666');
    }
}
