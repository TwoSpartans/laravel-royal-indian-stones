<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['eyebrow', 'heading_lead', 'heading_emphasis', 'introduction', 'caption', 'cover_image', 'cover_image_alt', 'edit_kicker', 'edit_heading', 'edit_post_ids', 'closer_kicker', 'closer_heading', 'closer_post_ids', 'maker_post_id', 'maker_eyebrow', 'maker_heading', 'maker_body', 'maker_label', 'maker_path', 'maker_image', 'maker_image_alt'])]
class JournalSetting extends Model
{
    public const CACHE_KEY = 'journal.settings';

    public function makerPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'maker_post_id');
    }

    public static function current(): self
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached instanceof self) {
            return $cached;
        }

        $settings = static::query()->first() ?? static::query()->create(static::defaults());
        Cache::forever(self::CACHE_KEY, $settings);

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'eyebrow' => 'The Journal',
            'heading_lead' => 'Stories in',
            'heading_emphasis' => 'Indian stone.',
            'introduction' => 'Notes on sandstone, marble, granite and the other stones we cut in Jodhpur, from the quarry face to the finished floor.',
            'caption' => 'Umaid Bhawan Palace, built in Jodhpur sandstone.',
            'cover_image_alt' => 'Umaid Bhawan Palace in Jodhpur, built in golden sandstone',
            'edit_kicker' => '01 / Latest',
            'edit_heading' => 'From the quarry to the building.',
            'closer_kicker' => '02 / A closer look',
            'closer_heading' => 'Know the materials. Care for the surface.',
            'maker_eyebrow' => 'The hands behind the craft',
            'maker_label' => 'Meet the makers',
        ];
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            self::forgetCache();
        });
        static::deleted(function (): void {
            self::forgetCache();
        });
    }

    protected function casts(): array
    {
        return [
            'edit_post_ids' => 'array',
            'closer_post_ids' => 'array',
        ];
    }
}
