<?php

namespace Database\Factories;

use App\Enums\OutreachProfileKind;
use App\Models\OutreachProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachProfile>
 */
class OutreachProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Google Business Profile',
            'url' => 'https://maps.google.com/?cid=1',
            'kind' => OutreachProfileKind::GoogleBusiness,
            'last_checked_on' => null,
            'gap' => null,
        ];
    }
}
