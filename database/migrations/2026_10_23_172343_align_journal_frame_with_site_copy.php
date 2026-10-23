<?php

use App\Models\JournalSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace(fromNew: false);
    }

    public function down(): void
    {
        $this->replace(fromNew: true);
    }

    private function replace(bool $fromNew): void
    {
        $settings = JournalSetting::query()->first();
        if (! $settings instanceof JournalSetting) {
            return;
        }

        $pairs = [
            'eyebrow' => ['The Royal Journal', 'The Journal'],
            'heading_lead' => ['Stories with', 'Stories in'],
            'heading_emphasis' => ['an Indian soul.', 'Indian stone.'],
            'introduction' => [
                'A celebration of Indian craftsmanship, thoughtful homes and the beauty of things made by hand.',
                'Notes on sandstone, marble, granite and the other stones we cut in Jodhpur, from the quarry face to the finished floor.',
            ],
            'caption' => [
                'A home with character, a story in every detail.',
                'Umaid Bhawan Palace, built in Jodhpur sandstone.',
            ],
            'cover_image_alt' => [
                'Carved wooden cabinet in a sunlit room with an arched doorway.',
                'Umaid Bhawan Palace in Jodhpur, built in golden sandstone',
            ],
            'edit_kicker' => ['01 / The Edit', '01 / Latest'],
            'edit_heading' => ['For a beautifully lived-in home.', 'From the quarry to the building.'],
            'closer_heading' => ['Know the materials. Love the details.', 'Know the materials. Care for the surface.'],
        ];

        $changed = false;
        foreach ($pairs as $field => [$previous, $current]) {
            $from = $fromNew ? $current : $previous;
            $to = $fromNew ? $previous : $current;
            if ($settings->{$field} === $from) {
                $settings->{$field} = $to;
                $changed = true;
            }
        }

        if ($changed) {
            $settings->save();
        }
    }
};
