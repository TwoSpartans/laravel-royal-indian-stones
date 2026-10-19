<?php

namespace App\Services\RichContent;

class BlockFrame
{
    public const WIDTHS = ['text', 'wide', 'full'];

    public const ALIGNS = ['left', 'center', 'right'];

    public const RATIOS = ['4:3', '1:1', '16:9', '3:2'];

    public const SCALES = ['editorial', 'standard', 'compact'];

    public const SPACINGS = ['compact', 'standard', 'relaxed'];

    public const BACKGROUNDS = ['none', 'ivory', 'forest', 'sand', 'gold'];

    public const RADII = ['none', 'soft', 'round'];

    public const VISIBILITIES = ['all', 'desktop', 'tablet', 'mobile'];

    /**
     * @return array{width: string, align: string, ratio: string, scale: string, spacing: string, background: string, radius: string, visibility: string}
     */
    public function defaults(): array
    {
        return [
            'width' => 'wide',
            'align' => 'left',
            'ratio' => '4:3',
            'scale' => 'editorial',
            'spacing' => 'standard',
            'background' => 'none',
            'radius' => 'soft',
            'visibility' => 'all',
        ];
    }

    /**
     * @return array{width: string, align: string, ratio: string, scale: string, spacing: string, background: string, radius: string, visibility: string}
     */
    public function sanitize(mixed $frame): array
    {
        $frame = is_array($frame) ? $frame : [];
        $sanitized = [];

        foreach ($this->defaults() as $key => $default) {
            $allowed = match ($key) {
                'width' => self::WIDTHS,
                'align' => self::ALIGNS,
                'ratio' => self::RATIOS,
                'scale' => self::SCALES,
                'spacing' => self::SPACINGS,
                'background' => self::BACKGROUNDS,
                'radius' => self::RADII,
                'visibility' => self::VISIBILITIES,
                default => [$default],
            };
            $value = (string) ($frame[$key] ?? $default);
            $sanitized[$key] = in_array($value, $allowed, true) ? $value : $default;
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>|null  $frame
     */
    public function classes(?array $frame): string
    {
        $frame = $this->sanitize($frame);

        return implode(' ', [
            'rich-frame',
            'rich-frame-width-'.$frame['width'],
            'rich-frame-align-'.$frame['align'],
            'rich-frame-scale-'.$frame['scale'],
            'rich-frame-space-'.$frame['spacing'],
            'rich-frame-bg-'.$frame['background'],
            'rich-frame-radius-'.$frame['radius'],
            'rich-frame-ratio-'.str_replace(':', 'x', $frame['ratio']),
            'rich-frame-show-'.$frame['visibility'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function options(): array
    {
        return [
            'width' => self::WIDTHS,
            'align' => self::ALIGNS,
            'ratio' => self::RATIOS,
            'scale' => self::SCALES,
            'spacing' => self::SPACINGS,
            'background' => self::BACKGROUNDS,
            'radius' => self::RADII,
            'visibility' => self::VISIBILITIES,
        ];
    }
}
