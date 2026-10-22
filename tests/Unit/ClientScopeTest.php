<?php

namespace Tests\Unit;

use App\Models\Client;
use Tests\TestCase;

class ClientScopeTest extends TestCase
{
    public function test_the_follow_up_rule(): void
    {
        $this->assertSame(360, Client::FOLLOW_UP_ALERT_DAYS);
    }

    public function test_clients_who_need_a_follow_up(): void
    {
        $days = Client::FOLLOW_UP_ALERT_DAYS;

        $never = Client::factory()->create(['last_follow_up' => null]);
        $old = Client::factory()->create(['last_follow_up' => now()->subDays($days + 1)->toDateString()]);
        Client::factory()->create(['last_follow_up' => now()->subDays($days)->toDateString()]);
        Client::factory()->create(['last_follow_up' => now()->subDays(5)->toDateString()]);

        $this->assertEqualsCanonicalizing(
            [$never->id, $old->id],
            Client::needsFollowUp()->pluck('id')->all(),
        );
    }
}
