<?php

namespace Database\Factories;

use App\Enums\OutreachTopicIntent;
use App\Models\OutreachTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachTopic>
 */
class OutreachTopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phrase' => 'mint sandstone tiles',
            'intent' => OutreachTopicIntent::Buy,
            'target_url' => '/sandstone-mint',
            'country' => 'India',
            'note' => null,
        ];
    }
}
