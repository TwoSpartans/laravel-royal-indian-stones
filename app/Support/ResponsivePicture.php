<?php

namespace App\Support;

use App\Models\Category;
use App\Models\JournalMedia;
use App\Models\Page;
use App\Models\Post;
use App\Models\ProductImage;
use App\Models\Project;
use Illuminate\Support\Facades\File;

class ResponsivePicture
{
    /** @var list<int> */
    public const WIDTHS = [480, 800];

    public const MAX_WIDTH = 1600;

    /**
     * @return array{fallback: string, avif: string|null, webp: string|null}
     */
    public function sources(string $src): array
    {
        $relative = $this->relative($src);
        if ($relative === null) {
            return ['fallback' => $this->fallbackUrl($src), 'avif' => null, 'webp' => null];
        }

        $size = @getimagesize(public_path($relative));
        if ($size === false || $this->isIcon((int) $size[0], (int) $size[1])) {
            return ['fallback' => asset($relative), 'avif' => null, 'webp' => null];
        }

        $widths = $this->targetWidths((int) $size[0]);

        return [
            'fallback' => asset($relative),
            'avif' => $this->srcset($relative, $widths, 'avif'),
            'webp' => $this->srcset($relative, $widths, 'webp'),
        ];
    }

    public function ensure(string $src): bool
    {
        if (! function_exists('imageavif') || ! function_exists('imagewebp')) {
            return false;
        }

        $relative = $this->relative($src);
        if ($relative === null) {
            return false;
        }

        $absolute = public_path($relative);
        $size = @getimagesize($absolute);
        if ($size === false || $this->isIcon((int) $size[0], (int) $size[1])) {
            return false;
        }

        $widths = $this->targetWidths((int) $size[0]);
        $missing = [];
        foreach ($widths as $width) {
            foreach (['avif', 'webp'] as $extension) {
                $variant = $this->variantRelative($relative, $width, $extension);
                if (! is_file(public_path($variant))) {
                    $missing[$width][$extension] = $variant;
                }
            }
        }

        if ($missing === []) {
            return true;
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            return false;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return false;
        }

        $this->prepare($image);
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        foreach ($missing as $width => $formats) {
            $height = max(1, (int) round($sourceHeight * ($width / max(1, $sourceWidth))));
            $frame = $width === $sourceWidth ? $image : imagescale($image, $width, $height, IMG_BICUBIC);
            if ($frame === false) {
                continue;
            }
            $this->prepare($frame);
            foreach ($formats as $extension => $variant) {
                $target = public_path($variant);
                File::ensureDirectoryExists(dirname($target));
                $written = $extension === 'avif'
                    ? imageavif($frame, $target, 50, 6)
                    : imagewebp($frame, $target, 75);
                if ($written !== true) {
                    if (is_file($target)) {
                        unlink($target);
                    }

                    return false;
                }
            }
            if ($frame !== $image) {
                unset($frame);
            }
        }

        unset($image);

        return true;
    }

    public function rewriteHtml(string $html): string
    {
        if (! str_contains(strtolower($html), '<img')) {
            return $html;
        }

        return preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
            return $this->pictureFromTag($match[0]);
        }, $html) ?? $html;
    }

    /**
     * @return list<string>
     */
    public function discover(): array
    {
        $paths = [
            'assets/img/about.jpg',
            'assets/img/slide/slide-1.jpg',
            'assets/img/slide/slide-3.jpg',
            'assets/img/slide/slide-5.jpg',
            'assets/img/slide/slide-6.jpg',
            'assets/blog/240127/jaisalmer-yellow-wall-clading-natural-golden-limestone-beauty.jpg',
            'assets/img/projects/black-granite-wall-cladding-slabs-design-cnc-work.jpg',
            'assets/img/projects/dholpur-beige-sandstone-wall-cladding-building-project.jpg',
            'assets/img/products/index/wall-panel/kandla-grey-sandstone-wall-panels.jpg',
            'assets/img/products/index/wall-cladding/lalitpur-yellow-sandstone-natural-wall-cladding.jpg',
            'assets/img/products/index/stone-kerb/mint-sandstone-natural-garden-paving-kerbs.jpg',
            'assets/img/products/index/stone-circle/lalitpur-yellow-sandstone-natural-finish-circle.jpg',
            'assets/img/products/index/monolith-stone/indian-slate-stone-natural-monoliths.jpg',
            'assets/img/optimized/d79b24d8c705b87b-800.webp',
            'assets/img/optimized/91f22ee6110e6580-800.webp',
            'assets/img/optimized/618cb9f3d72a9a6f-800.webp',
            'assets/img/optimized/824e38df12a9e0dd-800.webp',
            'assets/img/optimized/6dfef17b1801217e-800.webp',
            'assets/img/optimized/26ed9ec6ab715a3f-800.webp',
            'assets/img/optimized/9d56fc85bb11ee41-800.webp',
            'assets/img/optimized/a94e2016d80aa6c8-800.webp',
            'assets/img/optimized/1b160e9473f1bb7e-800.webp',
        ];

        $manifest = public_path('assets/data/images.json');
        if (is_file($manifest)) {
            $decoded = json_decode((string) file_get_contents($manifest), true);
            if (is_array($decoded)) {
                foreach (array_keys($decoded) as $key) {
                    $paths[] = (string) $key;
                }
            }
        }

        foreach ([ProductImage::class, Category::class, Project::class, Post::class, JournalMedia::class] as $model) {
            $column = $model === ProductImage::class || $model === JournalMedia::class ? 'path' : 'image';
            $paths = array_merge($paths, $model::query()->whereNotNull($column)->pluck($column)->all());
        }

        Page::query()->select(['id', 'body'])->orderBy('id')->chunk(40, function ($pages) use (&$paths): void {
            foreach ($pages as $page) {
                $paths = array_merge($paths, $this->sourcesInHtml((string) $page->body));
            }
        });

        Post::query()->select(['id', 'body'])->orderBy('id')->chunk(40, function ($posts) use (&$paths): void {
            foreach ($posts as $post) {
                $paths = array_merge($paths, $this->sourcesInHtml((string) $post->body));
            }
        });

        $unique = [];
        foreach ($paths as $path) {
            $relative = $this->relative((string) $path);
            if ($relative !== null) {
                $unique[$relative] = $relative;
            }
        }

        return array_values($unique);
    }

    /**
     * @return list<int>
     */
    public function targetWidths(int $originalWidth): array
    {
        $cap = min(max(1, $originalWidth), self::MAX_WIDTH);
        $widths = [];
        foreach (self::WIDTHS as $width) {
            if ($width < $cap) {
                $widths[] = $width;
            }
        }
        $widths[] = $cap;

        return array_values(array_unique($widths));
    }

    public function relative(string $src): ?string
    {
        $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($src === '' || str_starts_with($src, 'data:')) {
            return null;
        }

        $path = parse_url($src, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $src;
        }

        $path = rawurldecode(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (preg_match('/\.(jpe?g|png|webp)$/i', $path) !== 1) {
            return null;
        }

        return is_file(public_path($path)) ? $path : null;
    }

    /**
     * @param  list<int>  $widths
     */
    private function srcset(string $source, array $widths, string $extension): ?string
    {
        $parts = [];
        foreach ($widths as $width) {
            $path = $this->variantRelative($source, $width, $extension);
            if (is_file(public_path($path))) {
                $parts[] = $this->publicUrl($path).' '.$width.'w';
            }
        }

        if ($parts === [] && $extension === 'webp' && preg_match('/\.webp$/i', $source) === 1) {
            $size = @getimagesize(public_path($source));
            if ($size !== false) {
                return $this->publicUrl($source).' '.$size[0].'w';
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function publicUrl(string $relative): string
    {
        $segments = array_map(rawurlencode(...), explode('/', $relative));

        return '/'.implode('/', $segments);
    }

    private function variantRelative(string $source, int $width, string $extension): string
    {
        return 'assets/img/optimized/'.substr(md5($source), 0, 16).'-'.$width.'.'.$extension;
    }

    private function isIcon(int $width, int $height): bool
    {
        return max($width, $height) <= 64;
    }

    private function prepare(\GdImage $image): void
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    private function fallbackUrl(string $src): string
    {
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://') || str_starts_with($src, '/')) {
            return $src;
        }

        return asset(ltrim($src, '/'));
    }

    private function pictureFromTag(string $tag): string
    {
        $src = $this->attribute($tag, 'src');
        if ($src === null) {
            return $tag;
        }

        $set = $this->sources($src);
        if ($set['avif'] === null && $set['webp'] === null) {
            return $tag;
        }

        $sizes = $this->attribute($tag, 'sizes');
        $picture = '<picture>';
        if ($set['avif'] !== null) {
            $picture .= '<source type="image/avif" srcset="'.$this->escape($set['avif']).'"'.$this->sizesAttribute($sizes).'>';
        }
        if ($set['webp'] !== null) {
            $picture .= '<source type="image/webp" srcset="'.$this->escape($set['webp']).'"'.$this->sizesAttribute($sizes).'>';
        }

        $img = '<img src="'.$this->escape($set['fallback']).'" alt="'.$this->escape($this->attribute($tag, 'alt') ?? '').'"';
        foreach (['width', 'height', 'class', 'loading', 'decoding', 'fetchpriority'] as $name) {
            $value = $this->attribute($tag, $name);
            if ($value !== null && $value !== '') {
                $img .= ' '.$name.'="'.$this->escape($value).'"';
            }
        }
        $img .= '>';

        return $picture.$img.'</picture>';
    }

    private function sizesAttribute(?string $sizes): string
    {
        return $sizes === null || $sizes === '' ? '' : ' sizes="'.$this->escape($sizes).'"';
    }

    private function attribute(string $tag, string $name): ?string
    {
        if (preg_match('/\s'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/i', $tag, $match) !== 1) {
            return null;
        }

        return html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private function sourcesInHtml(string $html): array
    {
        if (preg_match_all('/<img\b[^>]*\ssrc\s*=\s*(["\'])(.*?)\1/i', $html, $matches) < 1) {
            return [];
        }

        return $matches[2];
    }
}
