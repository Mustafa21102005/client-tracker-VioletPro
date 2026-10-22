<?php

namespace Tests\Unit;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use App\Models\Attachment;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    public function test_the_whole_chain_is_connected_in_both_directions(): void
    {
        $attachment = Attachment::factory()->create();
        $edit = $attachment->changeRequest;
        $project = $edit->project;
        $company = $project->company;
        $client = $company->client;

        $this->assertTrue($client->companies->contains($company));
        $this->assertTrue($client->projects->contains($project));        // through the company
        $this->assertTrue($company->projects->contains($project));
        $this->assertTrue($company->changeRequests->contains($edit));    // through the project
        $this->assertTrue($project->changeRequests->contains($edit));
        $this->assertTrue($edit->attachments->contains($attachment));

        $this->assertTrue($company->client->is($client));
        $this->assertTrue($project->company->is($company));
        $this->assertTrue($edit->project->is($project));
        $this->assertTrue($attachment->changeRequest->is($edit));
    }

    public function test_values_are_converted_to_the_right_types(): void
    {
        $edit = ChangeRequest::factory()
            ->priced(750.5)
            ->paid(Carbon::parse('2026-03-01'))
            ->status(ChangeRequestStatus::Done)
            ->priority(Priority::Urgent)
            ->create()
            ->refresh();

        $this->assertSame('750.50', $edit->price);
        $this->assertSame(Priority::Urgent, $edit->priority);
        $this->assertSame(ChangeRequestStatus::Done, $edit->status);
        $this->assertInstanceOf(Carbon::class, $edit->paid_at);

        $client = Client::factory()->create(['last_follow_up' => '2026-03-01'])->refresh();

        $this->assertSame('2026-03-01', $client->last_follow_up->toDateString());
    }

    public static function softDeletingModels(): array
    {
        return [
            'client' => [Client::class],
            'company' => [Company::class],
            'project' => [Project::class],
            'edit' => [ChangeRequest::class],
        ];
    }

    #[DataProvider('softDeletingModels')]
    public function test_these_models_are_soft_deleted(string $class): void
    {
        $model = $class::factory()->create();

        $model->delete();

        $this->assertSoftDeleted($model);
    }

    public function test_attachments_are_deleted_for_real(): void
    {
        $attachment = Attachment::factory()->create();

        $attachment->delete();

        $this->assertModelMissing($attachment);
    }

    // The database itself refuses to erase a parent that still has children (a safety net behind the app's own rules).

    public function test_the_database_refuses_to_erase_a_client_that_has_companies(): void
    {
        $company = Company::factory()->create();

        $this->expectException(QueryException::class);

        $company->client->forceDelete();
    }

    public function test_the_database_refuses_to_erase_a_company_that_has_projects(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);

        $project->company->forceDelete();
    }

    public function test_the_database_refuses_to_erase_a_project_that_has_edits(): void
    {
        $edit = ChangeRequest::factory()->create();

        $this->expectException(QueryException::class);

        $edit->project->forceDelete();
    }
}
