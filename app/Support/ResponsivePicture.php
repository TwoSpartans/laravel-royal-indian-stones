<?php

namespace App\Support;

use App\Models\Category;
use App\Models\HomeImage;
use App\Models\JournalMedia;
use App\Models\JournalSetting;
use App\Models\Page;
use App\Models\Post;
use App\Models\ProductImage;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ResponsivePicture
{
    /** @var list<int> */
    public const WIDTHS = [160, 320, 480, 640, 720, 800, 1200];

    public const MAX_WIDTH = 1600;

    /**
     * @return array{fallback: string, avif: string|null, webp: string|null, width: int|null, height: int|null}
     */
    public function sources(string $src): array
    {
        $empty = ['fallback' => $this->fallbackUrl($src), 'avif' => null, 'webp' => null, 'width' => null, 'height' => null];
        $relative = $this->relative($src);
        if ($relative === null) {
            return $empty;
        }

        $size = @getimagesize($this->locatedFile($relative) ?? '');
        if ($size === false || $this->isIcon((int) $size[0], (int) $size[1])) {
            return ['fallback' => asset($relative), 'avif' => null, 'webp' => null, 'width' => null, 'height' => null];
        }

        $widths = $this->targetWidths((int) $size[0]);

        return [
            'fallback' => asset($relative),
            'avif' => $this->srcset($relative, $widths, 'avif'),
            'webp' => $this->srcset($relative, $widths, 'webp'),
            'width' => (int) $size[0],
            'height' => (int) $size[1],
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

        $absolute = $this->locatedFile($relative);
        if ($absolute === null) {
            return false;
        }
        $size = @getimagesize($absolute);
        if ($size === false || $this->isIcon((int) $size[0], (int) $size[1])) {
            return false;
        }

        $widths = $this->targetWidths((int) $size[0]);
        $missing = [];
        foreach ($widths as $width) {
            foreach (['avif', 'webp'] as $extension) {
                $variant = $this->variantRelative($relative, $width, $extension);
                if ($this->locatedFile($variant) === null) {
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
                $target = $this->absolutePath($variant);
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
                @chmod(dirname($target), 0755);
                @chmod($target, 0644);
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
            'assets/img/journal/design/cover-heritage.png',
            'assets/img/journal/design/maker-workshop.png',
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

        $journal = JournalSetting::query()->first();
        if ($journal instanceof JournalSetting) {
            foreach (['cover_image', 'maker_image'] as $column) {
                if (is_string($journal->{$column}) && $journal->{$column} !== '') {
                    $paths[] = $journal->{$column};
                }
            }
        }

        if (Schema::hasTable('home_images')) {
            $paths = array_merge($paths, HomeImage::query()->whereNotNull('path')->pluck('path')->all());
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

        return $this->locatedFile($path) !== null ? $path : null;
    }

    private function locatedFile(string $relative): ?string
    {
        if (str_starts_with($relative, 'storage/')) {
            $stored = storage_path('app/public/'.substr($relative, strlen('storage/')));
            if (is_file($stored)) {
                return $stored;
            }
        }

        $public = public_path($relative);

        return is_file($public) ? $public : null;
    }

    /**
     * @param  list<int>  $widths
     */
    private function srcset(string $source, array $widths, string $extension): ?string
    {
        $parts = [];
        foreach ($widths as $width) {
            $path = $this->variantRelative($source, $width, $extension);
            if ($this->locatedFile($path) !== null) {
                $parts[] = $this->publicUrl($path).' '.$width.'w';
            }
        }

        if ($parts === [] && $extension === 'webp' && preg_match('/\.webp$/i', $source) === 1) {
            $located = $this->locatedFile($source);
            $size = $located === null ? false : @getimagesize($located);
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
        $name = substr(md5($source), 0, 16).'-'.$width.'.'.$extension;
        if (str_starts_with($source, 'storage/')) {
            return 'storage/img/optimized/'.$name;
        }

        return 'assets/img/optimized/'.$name;
    }

    private function absolutePath(string $relative): string
    {
        if (str_starts_with($relative, 'storage/')) {
            return storage_path('app/public/'.substr($relative, strlen('storage/')));
        }

        return public_path($relative);
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

        $sizes = $this->layoutSizes($tag, $this->attribute($tag, 'sizes'));
        $picture = '<picture>';
        if ($set['avif'] !== null) {
            $picture .= '<source type="image/avif" srcset="'.$this->escape($set['avif']).'"'.$this->sizesAttribute($sizes).'>';
        }
        if ($set['webp'] !== null) {
            $picture .= '<source type="image/webp" srcset="'.$this->escape($set['webp']).'"'.$this->sizesAttribute($sizes).'>';
        }

        $img = '<img src="'.$this->escape($set['fallback']).'" alt="'.$this->escape($this->attribute($tag, 'alt') ?? '').'"';
        $dimensions = [
            'width' => $set['width'] !== null ? (string) $set['width'] : null,
            'height' => $set['height'] !== null ? (string) $set['height'] : null,
        ];
        foreach (['width', 'height', 'class', 'loading', 'decoding', 'fetchpriority'] as $name) {
            $value = $dimensions[$name] ?? $this->attribute($tag, $name);
            if ($value !== null && $value !== '') {
                $img .= ' '.$name.'="'.$this->escape($value).'"';
            }
        }
        $img .= '>';

        return $picture.$img.'</picture>';
    }

    private function layoutSizes(string $tag, ?string $sizes): string
    {
        if ($sizes !== null && $sizes !== '' && ! str_contains($sizes, '33vw')) {
            return $sizes;
        }

        $width = (int) ($this->attribute($tag, 'width') ?? 0);
        $class = $this->attribute($tag, 'class') ?? '';
        if ($width >= 1000 && ! str_contains($class, 'img-thumbnail')) {
            return '(max-width: 991px) calc(100vw - 32px), 100vw';
        }

        return '(max-width: 991px) calc(100vw - 32px), 720px';
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
