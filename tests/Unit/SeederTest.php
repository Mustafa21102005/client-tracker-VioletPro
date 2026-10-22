<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    public function test_seeding_creates_the_admin_and_realistic_demo_data(): void
    {
        config(['admin.name' => 'Test Admin', 'admin.email' => 'admin@example.com', 'admin.password' => 'a-long-password']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('Test Admin', $admin->name);
        $this->assertTrue(Hash::check('a-long-password', $admin->password));
        $this->assertSame(1, User::count());

        $this->assertGreaterThanOrEqual(10, Client::count());
        $this->assertGreaterThanOrEqual(10, Company::count());
        $this->assertGreaterThanOrEqual(10, Project::count());
        $this->assertGreaterThanOrEqual(50, ChangeRequest::count());
    }

    public function test_the_demo_data_follows_the_rules_the_app_enforces(): void
    {
        config(['admin.email' => 'admin@example.com', 'admin.password' => 'a-long-password']);

        $this->seed(DatabaseSeeder::class);

        // Phones are unique, only priced edits are ever paid, and nothing points at a missing parent.
        $this->assertSame(Client::count(), Client::query()->distinct()->count('phone'));
        $this->assertSame(0, ChangeRequest::whereNotNull('paid_at')->whereNull('price')->count());
        $this->assertSame(0, ChangeRequest::doesntHave('project')->count());
        $this->assertSame(0, Project::doesntHave('company')->count());
        $this->assertSame(0, Company::doesntHave('client')->count());
    }

    public function test_seeding_stops_with_a_clear_message_when_the_admin_login_is_missing(): void
    {
        config(['admin.email' => null, 'admin.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(DatabaseSeeder::class);
    }
}
