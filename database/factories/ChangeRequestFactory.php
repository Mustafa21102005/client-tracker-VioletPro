<?php

namespace Database\Factories;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use App\Models\ChangeRequest;
use App\Models\Project;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->unique()->sentence(3),
            'description' => null,
            'priority' => Priority::Medium,
            'price' => null,
            'status' => ChangeRequestStatus::Pending,
            'paid_at' => null,
        ];
    }

    // "States" are small tweaks you can chain: ChangeRequest::factory()->priced(500)->paid()->create()
    public function priority(Priority $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function status(ChangeRequestStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function priced(float $price): static
    {
        return $this->state(['price' => $price]);
    }

    public function paid(?DateTimeInterface $at = null): static
    {
        return $this->state(['paid_at' => $at ?? now()]);
    }
}
