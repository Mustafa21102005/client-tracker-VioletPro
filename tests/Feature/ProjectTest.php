<?php

namespace Tests\Feature;

use App\Enum\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\Company;
use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_a_project_can_be_added_to_a_company(): void
    {
        $company = Company::factory()->create();

        $this->post("/companies/{$company->id}/projects", ['name' => 'Online Store', 'description' => 'E-commerce site'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('projects', ['company_id' => $company->id, 'name' => 'Online Store']);
    }

    public function test_a_project_needs_a_name_and_a_reasonable_description(): void
    {
        $company = Company::factory()->create();

        $this->post("/companies/{$company->id}/projects", ['name' => ''])->assertSessionHasErrors('name');

        $this->post("/companies/{$company->id}/projects", ['name' => 'Shop', 'description' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('description');
    }

    public function test_a_project_can_be_updated(): void
    {
        $project = Project::factory()->create();

        $this->put("/projects/{$project->id}", ['name' => 'Renamed Project', 'description' => null])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Renamed Project']);
    }

    public function test_a_project_without_edits_is_soft_deleted(): void
    {
        $project = Project::factory()->create();

        $this->delete("/projects/{$project->id}")->assertSessionHas('success');

        $this->assertSoftDeleted($project);
    }

    public function test_a_project_with_edits_cannot_be_deleted(): void
    {
        $project = Project::factory()->create();
        ChangeRequest::factory()->for($project)->create();

        $this->delete("/projects/{$project->id}")->assertSessionHas('error');

        $this->assertNotSoftDeleted($project);
    }

    public function test_the_project_page_shows_its_context_and_correct_totals(): void
    {
        $project = Project::factory()->create();

        ChangeRequest::factory()->for($project)->status(ChangeRequestStatus::Done)->priced(1000)->paid()->create();
        ChangeRequest::factory()->for($project)->priced(500)->create(); // pending and unpaid
        ChangeRequest::factory()->for($project)->status(ChangeRequestStatus::Cancelled)->priced(300)->create(); // ignored in the money totals

        $this->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('projects/Show')
                ->where('project.id', $project->id)
                ->where('project.company.name', $project->company->name)
                ->where('project.company.client.name', $project->company->client->name)
                ->where('changeRequests.total', 3)
                ->where('stats.total', 3)
                ->where('stats.pending', 1)
                ->where('stats.done', 1)
                ->where('stats.active', 2)
                ->where('stats.total_value', fn($value) => (float) $value === 1500.0)
                ->where('stats.unpaid_value', fn($value) => (float) $value === 500.0));
    }

    public function test_the_project_search_endpoint_works_and_is_not_mistaken_for_a_project_id(): void
    {
        $project = Project::factory()->create(['name' => 'Online Shop']);
        Project::factory()->create(['name' => 'Something Else']);

        $this->getJson('/projects/search?q=Shop')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Online Shop')
            ->assertJsonPath('0.subtitle', "{$project->company->name} · {$project->company->client->name}");
    }
}
