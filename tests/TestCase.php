<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The tests load real pages, but they don't need the built CSS and JavaScript for that.
        $this->withoutVite();
    }

    // Refuse to continue unless this is a test database.
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_test') && $database !== ':memory:') {
            throw new RuntimeException("Refusing to run the tests on '{$database}'. Use a database whose name ends with _test.");
        }
    }

    protected function signIn(): User
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        return $user;
    }
}
