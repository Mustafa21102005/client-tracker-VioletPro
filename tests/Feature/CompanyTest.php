<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_a_company_can_be_added_to_a_client(): void
    {
        $client = Client::factory()->create();

        $this->post("/clients/{$client->id}/companies", ['name' => 'Acme Trading', 'description' => 'Main branch'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', ['client_id' => $client->id, 'name' => 'Acme Trading']);
    }

    public function test_a_company_needs_a_name(): void
    {
        $client = Client::factory()->create();

        $this->post("/clients/{$client->id}/companies", ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_a_client_cannot_have_two_companies_with_the_same_name(): void
    {
        $client = Client::factory()->create();
        Company::factory()->for($client)->create(['name' => 'Acme Trading']);

        $this->post("/clients/{$client->id}/companies", ['name' => 'Acme Trading'])->assertSessionHasErrors('name');
    }

    public function test_two_different_clients_can_each_have_a_company_with_the_same_name(): void
    {
        Company::factory()->create(['name' => 'Acme Trading']);
        $otherClient = Client::factory()->create();

        $this->post("/clients/{$otherClient->id}/companies", ['name' => 'Acme Trading'])->assertSessionHasNoErrors();
    }

    public function test_the_name_of_a_deleted_company_can_be_used_again(): void
    {
        $client = Client::factory()->create();
        $company = Company::factory()->for($client)->create(['name' => 'Acme Trading']);
        $company->delete();

        $this->post("/clients/{$client->id}/companies", ['name' => 'Acme Trading'])->assertSessionHasNoErrors();
    }

    public function test_a_company_can_be_updated_without_clashing_with_itself(): void
    {
        $company = Company::factory()->create(['name' => 'Acme Trading']);

        $this->put("/companies/{$company->id}", ['name' => 'Acme Trading', 'description' => 'Updated note'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'description' => 'Updated note']);
    }

    public function test_a_company_cannot_take_the_name_of_a_sibling_company(): void
    {
        $client = Client::factory()->create();
        Company::factory()->for($client)->create(['name' => 'First Co']);
        $second = Company::factory()->for($client)->create(['name' => 'Second Co']);

        $this->put("/companies/{$second->id}", ['name' => 'First Co'])->assertSessionHasErrors('name');
    }

    public function test_a_company_without_projects_is_soft_deleted(): void
    {
        $company = Company::factory()->create();

        $this->delete("/companies/{$company->id}")->assertSessionHas('success');

        $this->assertSoftDeleted($company);
    }

    public function test_a_company_with_projects_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        Project::factory()->for($company)->create();

        $this->delete("/companies/{$company->id}")->assertSessionHas('error');

        $this->assertNotSoftDeleted($company);
    }

    public function test_the_company_page_shows_its_client_projects_and_edit_count(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->for($company)->create();
        Project::factory()->count(2)->for($company)->create();
        ChangeRequest::factory()->count(2)->for($project)->create();

        $this->get("/companies/{$company->id}")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('companies/Show')
                ->where('company.id', $company->id)
                ->where('company.client.id', $company->client_id)
                ->where('projects.total', 3)
                ->where('changeRequestsCount', 2));
    }

    public function test_the_company_search_endpoint_matches_the_company_or_its_client(): void
    {
        $client = Client::factory()->create(['name' => 'Fahad Otaibi']);
        Company::factory()->for($client)->create(['name' => 'Falcon Real Estate']);
        Company::factory()->create(['name' => 'Unrelated Co']);

        $this->getJson('/companies/search?q=Falcon')->assertJsonCount(1)->assertJsonPath('0.title', 'Falcon Real Estate');
        $this->getJson('/companies/search?q=Otaibi')->assertJsonCount(1)->assertJsonPath('0.subtitle', 'Fahad Otaibi');
    }
}
