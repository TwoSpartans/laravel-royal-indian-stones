<?php

namespace App\Support;

use App\Models\Category;
use App\Models\HomeImage;
use App\Models\HomeSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class HomeContent
{
    /** @var list<string> */
    public const EXCLUDED_COLLECTIONS = ['stone-applications', 'stone-articles'];

    /** @var Collection<string, HomeImage>|null */
    private ?Collection $images = null;

    /** @var Collection<string, HomeSection>|null */
    private ?Collection $sections = null;

    /**
     * @return array<string, array<string, string>>
     */
    public static function sectionDefaults(): array
    {
        return [
            'supplier' => [
                'heading' => 'Natural Indian Stones Supplier, Manufacturer and Exporter',
                'body' => 'The Royal Indian Stones evolved into Production and Manufacturing of Blocks, Flooring Slabs, Flooring Tiles, Calibrated - Ready to Fix Tiles, Monuments, Tomb Stones, Sculptures, Artefacts, Cobbles, Cubes, Kerbs, Pebbles, Landscaping Stones, Fireplaces, Fountains, Balusters, Slate Veneers, Mosaics, Stone Articles, Paving Stones, Tumbled Pebbles Stones, Natural River Pebbles Stones and Landscaping Garden Stones.',
            ],
            'explore' => [
                'heading' => 'Explore the collection',
                'body' => 'Sandstone, limestone, marble, granite, slate and the other stones we cut in India, chosen for floors, walls, roofs and gardens.',
            ],
            'stones' => [
                'heading' => 'Natural Indian Stones',
                'body' => 'Shaped over millions of years, natural Indian stone brings distinct textures to walls, pathways, roofs and architectural details. Explore panels, cladding, steps, kerbs, mosaics and veneers alongside our wider stone range. We supply, manufacture and export natural stone products from India.',
            ],
            'journal' => [
                'heading' => 'Notes on Indian stone',
                'button_label' => 'Read the blog →',
            ],
            'enquiry' => [
                'eyebrow' => 'Let’s discuss your project',
                'heading' => 'Found a stone you like?',
                'body' => 'Send your requirements for a product-specific quotation.',
                'button_label' => 'Request a Quote →',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function copy(string $key): array
    {
        $defaults = self::sectionDefaults()[$key];
        $stored = $this->sections()->get($key);

        $copy = [];
        foreach ($defaults as $field => $fallback) {
            $value = $stored?->{$field};
            $copy[$field] = is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
        }

        return $copy;
    }

    /**
     * @return list<array{slot: string, image: string, alt: string, eyebrow: string, title: string, subtitle: string, text: string, url: string, label: string, custom: bool, defaults: array{image: string, alt: string, eyebrow: string, title: string, subtitle: string, text: string, url: string, label: string}}>
     */
    public function hero(): array
    {
        $slides = [];
        foreach ($this->heroDefinitions() as $slide) {
            $resolved = $this->resolve('hero', $slide['slot'], $slide['image'], $slide['alt']);
            $row = $this->rows()->get('hero.'.$slide['slot']);
            $subtitle = $slide['slot'] === 'sandstone' ? 'For the spaces you imagine.' : '';
            $slides[] = [
                'slot' => $slide['slot'],
                'image' => $resolved['image'],
                'alt' => $resolved['alt'],
                'custom' => $resolved['custom'],
                'eyebrow' => $this->text($row?->eyebrow, $slide['eyebrow']),
                'title' => $this->text($row?->title, $slide['title']),
                'subtitle' => $subtitle === '' ? '' : $this->text($row?->subtitle, $subtitle),
                'text' => $this->text($row?->body, $slide['text']),
                'url' => $this->link($row?->link, $slide['url']),
                'label' => $this->text($row?->button_label, $slide['label']),
                'defaults' => [
                    'image' => $slide['image'],
                    'alt' => $slide['alt'],
                    'eyebrow' => $slide['eyebrow'],
                    'title' => $slide['title'],
                    'subtitle' => $subtitle,
                    'text' => $slide['text'],
                    'url' => $slide['url'],
                    'label' => $slide['label'],
                ],
            ];
        }

        return $slides;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<array{slot: string, name: string, url: string, image: string, alt: string, custom: bool, fallback: string}>
     */
    public function explore(Collection $categories): array
    {
        $covers = CategoryCovers::paths();
        $cards = [];

        foreach ($categories as $category) {
            if (in_array($category->slug, self::EXCLUDED_COLLECTIONS, true)) {
                continue;
            }

            $cover = $covers[$category->slug] ?? '';
            if ($cover !== '' && ! is_file(public_path($cover))) {
                $cover = '';
            }
            $fallback = $cover !== '' ? $cover : (string) $category->image;
            $resolved = $this->resolve('explore', $category->slug, $fallback, $category->name.' stone collection');
            $cards[] = [
                'slot' => $category->slug,
                'name' => $category->name,
                'url' => '/'.$category->slug,
                'image' => $resolved['image'],
                'alt' => $resolved['alt'],
                'custom' => $resolved['custom'],
                'fallback' => $fallback,
            ];
        }

        return $cards;
    }

    /**
     * @return list<array{slot: string, name: string, url: string, image: string, alt: string, custom: bool, fallback: string, default_name: string, default_url: string}>
     */
    public function stones(): array
    {
        $cards = [];
        foreach ($this->stoneDefinitions() as $stone) {
            $resolved = $this->resolve('stones', $stone['slot'], $stone['image'], $stone['name']);
            $row = $this->rows()->get('stones.'.$stone['slot']);
            $cards[] = [
                'slot' => $stone['slot'],
                'name' => $this->text($row?->title, $stone['name']),
                'url' => $this->link($row?->link, '/'.$stone['slot']),
                'image' => $resolved['image'],
                'alt' => $resolved['alt'],
                'custom' => $resolved['custom'],
                'fallback' => $stone['image'],
                'default_name' => $stone['name'],
                'default_url' => '/'.$stone['slot'],
            ];
        }

        return $cards;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<string>
     */
    public function slots(string $section, Collection $categories): array
    {
        $slots = match ($section) {
            'hero' => array_column($this->heroDefinitions(), 'slot'),
            'stones' => array_column($this->stoneDefinitions(), 'slot'),
            'explore' => $categories
                ->reject(fn (Category $category): bool => in_array($category->slug, self::EXCLUDED_COLLECTIONS, true))
                ->pluck('slug')
                ->values()
                ->all(),
            default => [],
        };

        return array_values(array_map(fn (mixed $slot): string => (string) $slot, $slots));
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<array{key: string, title: string, intro: string, save: string, kind: string, fields: list<array{name: string, label: string, value: string, multiline: bool}>, items: list<array<string, mixed>>}>
     */
    public function groups(Collection $categories): array
    {
        return [
            $this->slidePanel(),
            $this->copyPanel('supplier', 'Supplier line', 'The heading and paragraph under the opening slides.', 'Save supplier line', ['heading', 'body']),
            $this->explorePanel($categories),
            $this->stonePanel(),
            $this->copyPanel('journal', 'Journal', 'The heading and link above the latest notes. The notes themselves come from the blog.', 'Save journal', ['heading', 'button_label']),
            $this->copyPanel('enquiry', 'Closing band', 'The last invitation on the home page.', 'Save closing band', ['eyebrow', 'heading', 'body', 'button_label']),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return array{key: string, title: string, intro: string, save: string, kind: string, fields: list<array{name: string, label: string, value: string, multiline: bool}>, items: list<array<string, mixed>>}
     */
    private function copyPanel(string $key, string $title, string $intro, string $save, array $names): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'intro' => $intro,
            'save' => $save,
            'kind' => 'copy',
            'fields' => $this->fields($key, $names),
            'items' => [],
        ];
    }

    /**
     * @return array{key: string, title: string, intro: string, save: string, kind: string, fields: list<array{name: string, label: string, value: string, multiline: bool}>, items: list<array<string, mixed>>}
     */
    private function slidePanel(): array
    {
        return [
            'key' => 'hero',
            'title' => 'Opening slides',
            'intro' => 'Each slide has its own photograph and words. Leave a field blank to keep the current wording.',
            'save' => 'Save opening slides',
            'kind' => 'slides',
            'fields' => [],
            'items' => array_map(fn (array $slide): array => [
                'slot' => $slide['slot'],
                'heading' => $slide['title'],
                'image' => $slide['image'],
                'alt' => $slide['alt'],
                'custom' => $slide['custom'],
                'eyebrow' => $slide['eyebrow'],
                'title' => $slide['title'],
                'subtitle' => $slide['subtitle'],
                'text' => $slide['text'],
                'label' => $slide['label'],
                'url' => $slide['url'],
                'second_line' => $slide['slot'] === 'sandstone',
                'defaults' => $slide['defaults'],
            ], $this->hero()),
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{key: string, title: string, intro: string, save: string, kind: string, fields: list<array{name: string, label: string, value: string, multiline: bool}>, items: list<array<string, mixed>>}
     */
    private function explorePanel(Collection $categories): array
    {
        return [
            'key' => 'explore',
            'title' => 'Explore the collection',
            'intro' => 'Edit the section introduction and manage the category images as a visual collection.',
            'save' => 'Save section',
            'kind' => 'explore',
            'fields' => $this->fields('explore', ['heading', 'body']),
            'items' => array_map(fn (array $card): array => [
                'slot' => $card['slot'],
                'heading' => $card['name'],
                'detail' => $card['url'],
                'image' => $card['image'],
                'alt' => $card['alt'],
                'custom' => $card['custom'],
                'fallback' => $card['fallback'],
            ], $this->explore($categories)),
        ];
    }

    /**
     * @return array{key: string, title: string, intro: string, save: string, kind: string, fields: list<array{name: string, label: string, value: string, multiline: bool}>, items: list<array<string, mixed>>}
     */
    private function stonePanel(): array
    {
        return [
            'key' => 'stones',
            'title' => 'Natural Indian Stones',
            'intro' => 'Manage item names, homepage photos, descriptions and existing page links.',
            'save' => 'Save section',
            'kind' => 'stones',
            'fields' => $this->fields('stones', ['heading', 'body']),
            'items' => array_map(fn (array $card): array => [
                'slot' => $card['slot'],
                'heading' => $card['name'],
                'name' => $card['name'],
                'url' => $card['url'],
                'image' => $card['image'],
                'alt' => $card['alt'],
                'custom' => $card['custom'],
                'fallback' => $card['fallback'],
                'default_name' => $card['default_name'],
                'default_url' => $card['default_url'],
            ], $this->stones()),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return list<array{name: string, label: string, value: string, multiline: bool, max: int}>
     */
    private function fields(string $key, array $names): array
    {
        $copy = $this->copy($key);
        $labels = [
            'eyebrow' => 'Eyebrow',
            'heading' => 'Heading',
            'body' => 'Paragraph',
            'button_label' => 'Button',
        ];
        $fields = [];
        foreach ($names as $name) {
            $fields[] = [
                'name' => $name,
                'label' => $labels[$name],
                'value' => $copy[$name] ?? '',
                'multiline' => $name === 'body',
                'max' => match (true) {
                    $name === 'body' && $key === 'enquiry' => 500,
                    $name === 'body' => 1200,
                    $name === 'eyebrow', $name === 'button_label' => 80,
                    default => 160,
                },
            ];
        }

        return $fields;
    }

    private function text(?string $stored, string $fallback): string
    {
        $stored = is_string($stored) ? trim($stored) : '';

        return $stored !== '' ? $stored : $fallback;
    }

    private function link(?string $stored, string $fallback): string
    {
        $stored = is_string($stored) ? trim($stored) : '';
        if ($stored === '') {
            return $fallback;
        }

        $path = JournalDestination::normalize($stored);
        if ($path !== null && JournalDestination::exists($path)) {
            return $path;
        }

        return $fallback;
    }

    /**
     * @return Collection<string, HomeSection>
     */
    private function sections(): Collection
    {
        if ($this->sections instanceof Collection) {
            return $this->sections;
        }

        $this->sections = HomeSection::query()->get()->keyBy('key');

        return $this->sections;
    }

    /**
     * @return array{image: string, alt: string, custom: bool}
     */
    private function resolve(string $section, string $slot, string $fallback, string $fallbackAlt): array
    {
        $row = $this->rows()->get($section.'.'.$slot);
        if ($row instanceof HomeImage) {
            $this->publishLegacyHomeFile($row);
        }
        $stored = $row?->path;
        $custom = is_string($stored)
            && $stored !== ''
            && ! str_contains($stored, '..')
            && $this->storedFileExists($stored);
        $alt = $row?->alt;

        return [
            'image' => $custom ? $stored : $fallback,
            'alt' => is_string($alt) && trim($alt) !== '' ? trim($alt) : $fallbackAlt,
            'custom' => $custom,
        ];
    }

    private function publishLegacyHomeFile(HomeImage $image): void
    {
        $path = str_replace('\\', '/', (string) $image->path);
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'assets/img/home/')) {
            return;
        }

        $source = public_path($path);
        if (! is_file($source)) {
            return;
        }

        $relative = substr($path, strlen('assets/img/'));
        $destination = storage_path('app/public/'.$relative);
        File::ensureDirectoryExists(dirname($destination));
        if (! is_file($destination)) {
            File::copy($source, $destination);
        }
        @chmod(dirname($destination), 0755);
        @chmod($destination, 0644);

        $image->path = 'storage/'.$relative;
        $image->save();
    }

    private function storedFileExists(string $path): bool
    {
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, 'storage/')) {
            return is_file(storage_path('app/public/'.substr($path, strlen('storage/'))));
        }

        return is_file(public_path($path));
    }

    /**
     * @return Collection<string, HomeImage>
     */
    private function rows(): Collection
    {
        if ($this->images instanceof Collection) {
            return $this->images;
        }

        $this->images = HomeImage::query()->get()->keyBy(
            fn (HomeImage $image): string => $image->section.'.'.$image->slot,
        );

        return $this->images;
    }

    /**
     * @return list<array{slot: string, image: string, alt: string, eyebrow: string, title: string, text: string, url: string, label: string}>
     */
    private function heroDefinitions(): array
    {
        return [
            ['slot' => 'sandstone', 'image' => 'assets/img/slide/slide-1.jpg', 'alt' => 'Umaid Bhawan Palace in Jodhpur, built in golden sandstone', 'eyebrow' => 'Sandstone · Jodhpur, India', 'title' => 'Indian natural stone.', 'text' => 'This palace is built from Jodhpur sandstone. We supply the same warm stone in tiles, slabs and custom articles, matched for colour and finish.', 'url' => '/sandstone', 'label' => 'Explore sandstone'],
            ['slot' => 'limestone', 'image' => 'assets/blog/240127/jaisalmer-yellow-wall-clading-natural-golden-limestone-beauty.jpg', 'alt' => 'Golden Jaisalmer limestone cladding on a wall', 'eyebrow' => 'Limestone · flooring and steps', 'title' => 'Quiet stone for floors and steps.', 'text' => 'Tandur and Kota limestone take a honed or leather finish and stay even in colour across large floors, courtyards and staircases.', 'url' => '/limestone', 'label' => 'Explore limestone'],
            ['slot' => 'marble', 'image' => 'assets/img/slide/slide-3.jpg', 'alt' => 'The Taj Mahal, built in white marble', 'eyebrow' => 'Marble · monuments and interiors', 'title' => 'Marble with a long memory.', 'text' => 'White and coloured Indian marbles are cut for flooring, wall cladding, vanities and feature work, with the veining selected before it is packed.', 'url' => '/marble', 'label' => 'Explore marble'],
            ['slot' => 'granite', 'image' => 'assets/img/projects/black-granite-wall-cladding-slabs-design-cnc-work.jpg', 'alt' => 'Carved black granite wall cladding', 'eyebrow' => 'Granite · worktops and facades', 'title' => 'Granite that stays dense and dark.', 'text' => 'Absolute Black and other Indian granites are polished or flamed for kitchen tops, cladding and exterior paving that has to take wear.', 'url' => '/granite', 'label' => 'Explore granite'],
            ['slot' => 'slate', 'image' => 'assets/img/slide/slide-5.jpg', 'alt' => 'Stone-clad house with a slate exterior', 'eyebrow' => 'Slate · roofs and walls', 'title' => 'Slate for roofs, walls and floors.', 'text' => 'Natural cleft slate splits into thin, textured pieces for roofing, wall cladding and floors, with the surface left as the quarry split it.', 'url' => '/slate', 'label' => 'Explore slate'],
            ['slot' => 'pebbles', 'image' => 'assets/img/slide/slide-6.jpg', 'alt' => 'White pebble garden with stone stepping paths', 'eyebrow' => 'Pebbles · gardens and water', 'title' => 'Pebbles for paths and water.', 'text' => 'River and tumbled pebbles are graded by colour and size for garden paths, courtyards, water features and decorative fills.', 'url' => '/stone-pebbles', 'label' => 'Explore pebbles'],
        ];
    }

    /**
     * @return list<array{slot: string, name: string, image: string}>
     */
    private function stoneDefinitions(): array
    {
        return [
            ['slot' => 'index-stone-wall-panel', 'name' => 'Stone Wall Panel', 'image' => 'assets/img/products/index/wall-panel/kandla-grey-sandstone-wall-panels.jpg'],
            ['slot' => 'index-stone-wall-cladding', 'name' => 'Stone Wall Cladding', 'image' => 'assets/img/products/index/wall-cladding/lalitpur-yellow-sandstone-natural-wall-cladding.jpg'],
            ['slot' => 'index-stone-step', 'name' => 'Natural Stone Step', 'image' => 'assets/img/optimized/d79b24d8c705b87b-800.webp'],
            ['slot' => 'index-stone-kerb', 'name' => 'Natural Stone Kerb', 'image' => 'assets/img/products/index/stone-kerb/mint-sandstone-natural-garden-paving-kerbs.jpg'],
            ['slot' => 'index-stone-lintel', 'name' => 'Natural Stone Lintel', 'image' => 'assets/img/optimized/91f22ee6110e6580-800.webp'],
            ['slot' => 'index-stone-stepping', 'name' => 'Natural Stepping Stone', 'image' => 'assets/img/optimized/618cb9f3d72a9a6f-800.webp'],
            ['slot' => 'index-stone-palisade', 'name' => 'Natural Stone Palisade', 'image' => 'assets/img/optimized/824e38df12a9e0dd-800.webp'],
            ['slot' => 'index-stone-crazy', 'name' => 'Natural Crazy Stone', 'image' => 'assets/img/optimized/6dfef17b1801217e-800.webp'],
            ['slot' => 'index-stone-circle', 'name' => 'Natural Stone Circle', 'image' => 'assets/img/products/index/stone-circle/lalitpur-yellow-sandstone-natural-finish-circle.jpg'],
            ['slot' => 'index-stone-pier-cap', 'name' => 'Stone Pier Cap', 'image' => 'assets/img/optimized/26ed9ec6ab715a3f-800.webp'],
            ['slot' => 'index-stone-roof-tile', 'name' => 'Stone Roof Tile', 'image' => 'assets/img/optimized/9d56fc85bb11ee41-800.webp'],
            ['slot' => 'index-stone-monolith', 'name' => 'Natural Monolith Stone', 'image' => 'assets/img/products/index/monolith-stone/indian-slate-stone-natural-monoliths.jpg'],
            ['slot' => 'index-stone-veneer', 'name' => 'Natural Stone Veneer', 'image' => 'assets/img/optimized/a94e2016d80aa6c8-800.webp'],
            ['slot' => 'index-stone-mosaic', 'name' => 'Natural Stone Mosaic', 'image' => 'assets/img/optimized/1b160e9473f1bb7e-800.webp'],
        ];
    }
}
