<?php

namespace Database\Factories;

use App\Enums\OutreachMentionStatus;
use App\Models\OutreachMention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachMention>
 */
class OutreachMentionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication' => 'Stone World',
            'contact_name' => null,
            'offered_url' => '/sandstone-mint',
            'status' => OutreachMentionStatus::ToContact,
            'published_url' => null,
            'note' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => OutreachMentionStatus::Published,
            'published_url' => 'https://example.com/mint-sandstone',
        ]);
    }
}
