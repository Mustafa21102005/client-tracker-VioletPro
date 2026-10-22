<?php

namespace Tests\Feature;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Closure;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    // JSON turns 750.0 into a float, so money is compared with a tiny tolerance instead of ===.
    private function money(float $expected): Closure
    {
        return fn($value) => abs((float) $value - $expected) < 0.001;
    }

    // Five edits with known money, used by several tests below.
    private function seedMoney(): void
    {
        ChangeRequest::factory()->status(ChangeRequestStatus::Done)->priced(1000)->paid()->create(['title' => 'Paid this month']);
        ChangeRequest::factory()->status(ChangeRequestStatus::Done)->priced(700)->paid(now()->startOfMonth()->subDay())->create(['title' => 'Paid last month']);
        ChangeRequest::factory()->status(ChangeRequestStatus::Done)->priced(500)->create(['title' => 'Done but unpaid']);
        ChangeRequest::factory()->priced(250)->create(['title' => 'Pending and unpaid']);
        ChangeRequest::factory()->status(ChangeRequestStatus::Cancelled)->priced(300)->create(['title' => 'Cancelled']);
    }

    public function test_the_dashboard_works_when_there_is_no_data(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Dashboard')
                ->where('totals.clients', 0)
                ->where('kpis.outstanding', $this->money(0))
                ->where('kpis.collected_this_month', $this->money(0))
                ->where('kpis.pending', 0)
                ->where('kpis.follow_ups_due', 0)
                ->has('revenue', 6)
                ->where('statusCounts.done', 0)
                ->has('followUps', 0)
                ->has('unpaid', 0)
                ->has('attention', 0));
    }

    public function test_the_totals_count_clients_companies_and_projects(): void
    {
        $company = Company::factory()->create();
        Project::factory()->count(3)->for($company)->create();
        Client::factory()->create();

        $this->get('/dashboard')
            ->assertInertia(fn(Assert $page) => $page
                ->where('totals.clients', 2)
                ->where('totals.companies', 1)
                ->where('totals.projects', 3));
    }

    public function test_the_money_cards_add_up_correctly(): void
    {
        $this->seedMoney();

        $this->get('/dashboard')
            ->assertInertia(fn(Assert $page) => $page
                // Owed: 500 (done, unpaid) + 250 (pending, unpaid). The cancelled 300 is never owed.
                ->where('kpis.outstanding', $this->money(750))
                ->where('kpis.outstanding_count', 2)
                // Collected this month: only the 1000 that was paid today (the 700 was paid last month).
                ->where('kpis.collected_this_month', $this->money(1000))
                ->where('kpis.pending', 1));
    }

    public function test_the_status_breakdown_counts_every_status(): void
    {
        $this->seedMoney();

        $this->get('/dashboard')
            ->assertInertia(fn(Assert $page) => $page
                ->where('statusCounts.done', 3)
                ->where('statusCounts.pending', 1)
                ->where('statusCounts.cancelled', 1));
    }

    public function test_the_revenue_chart_shows_payments_in_the_right_months(): void
    {
        $this->seedMoney();

        $this->get('/dashboard')
            ->assertInertia(fn(Assert $page) => $page
                ->has('revenue', 6)
                ->where('revenue.5.label', now()->format('M'))
                ->where('revenue.5.total', $this->money(1000))
                ->where('revenue.4.label', now()->startOfMonth()->subMonth()->format('M'))
                ->where('revenue.4.total', $this->money(700))
                ->where('revenue.0.total', $this->money(0)));
    }

    public function test_the_unpaid_list_shows_done_work_first_and_skips_cancelled_edits(): void
    {
        $this->seedMoney();

        $this->get('/dashboard')
            ->assertInertia(fn(Assert $page) => $page
                ->has('unpaid', 2)
                ->where('unpaid.0.title', 'Done but unpaid')
                ->where('unpaid.0.price', $this->money(500))
                ->where('unpaid.1.title', 'Pending and unpaid')
                ->where('unpaid.0.client', fn($name) => is_string($name) && $name !== '')
                ->where('unpaid.0.company', fn($name) => is_string($name) && $name !== '')
                ->where('unpaid.0.project', fn($name) => is_string($name) && $name !== ''));
    }

    public function test_the_urgent_list_shows_pending_high_and_urgent_edits_urgent_first(): void
    {
        ChangeRequest::factory()->priority(Priority::High)->create(['title' => 'High pending']);
        ChangeRequest::factory()->priority(Priority::Urgent)->create(['title' => 'Urgent pending']);
        ChangeRequest::factory()->priority(Priority::Medium)->create(['title' => 'Medium pending']); // not important enough
        ChangeRequest::factory()->priority(Priority::Urgent)->status(ChangeRequestStatus::Done)->create(['title' => 'Urgent but done']); // already finished

        $this->get('/dashboard')
            ->assertInertia(fn(Assert $page) => $page
                ->has('attention', 2)
                ->where('attention.0.title', 'Urgent pending')
                ->where('attention.1.title', 'High pending'));
    }

    public function test_the_follow_up_list_and_card_use_the_alert_rule(): void
    {
        $days = Client::FOLLOW_UP_ALERT_DAYS;

        Client::factory()->create(['name' => 'Never Contacted', 'last_follow_up' => null]);
        Client::factory()->create(['name' => 'Old Contact', 'last_follow_up' => now()->subDays($days + 1)->toDateString()]);
        Client::factory()->create(['name' => 'On The Limit', 'last_follow_up' => now()->subDays($days)->toDateString()]);
        Client::factory()->create(['name' => 'Recent Contact', 'last_follow_up' => now()->subDays(2)->toDateString()]);

        $this->get('/dashboard')
            ->assertInertia(fn(Assert $page) => $page
                ->where('followUpDays', $days)
                ->where('kpis.follow_ups_due', 2)
                ->has('followUps', 2)
                ->where('followUps.0.name', 'Never Contacted') // never contacted comes first
                ->where('followUps.1.name', 'Old Contact'));
    }
}
