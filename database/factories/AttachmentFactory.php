<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\ChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'change_request_id' => ChangeRequest::factory(),
            'note' => null,
        ];
    }
}
