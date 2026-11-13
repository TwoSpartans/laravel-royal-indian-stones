<?php

namespace App\Services\RichContent;

class BlockRegistry
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return list<array{
     *   type: string,
     *   name: string,
     *   description: string,
     *   group: string,
     *   icon: string,
     *   nodeVersion: int
     * }>
     */
    public function blocks(): array
    {
        return [
            [
                'type' => 'paragraph',
                'name' => 'Paragraph',
                'description' => 'Body text for the article.',
                'group' => 'Writing',
                'icon' => 'fa-solid fa-paragraph',
                'nodeVersion' => 1,
            ],
            [
                'type' => 'heading',
                'name' => 'Heading',
                'description' => 'Section heading. Body headings are H2 and H3 only.',
                'group' => 'Writing',
                'icon' => 'fa-solid fa-heading',
                'nodeVersion' => 1,
            ],
            [
                'type' => 'image',
                'name' => 'Image',
                'description' => 'A single photograph with context-specific alt text.',
                'group' => 'Media',
                'icon' => 'fa-solid fa-image',
                'nodeVersion' => 1,
            ],
            [
                'type' => 'productSpotlight',
                'name' => 'Product Spotlight',
                'description' => 'A live product card resolved from the catalog.',
                'group' => 'Commerce',
                'icon' => 'fa-solid fa-couch',
                'nodeVersion' => 1,
            ],
            [
                'type' => 'workshopStory',
                'name' => 'Workshop Story',
                'description' => 'Process photography and editorial craft notes.',
                'group' => 'Craft',
                'icon' => 'fa-solid fa-hammer',
                'nodeVersion' => 1,
            ],
            [
                'type' => 'cta',
                'name' => 'Call to Action',
                'description' => 'A heading, supporting line, and a single safe link.',
                'group' => 'Conversion',
                'icon' => 'fa-solid fa-arrow-right',
                'nodeVersion' => 1,
            ],
            [
                'type' => 'gallery',
                'name' => 'Gallery',
                'description' => 'Several photographs in a grid or a carousel.',
                'group' => 'Media',
                'icon' => 'fa-solid fa-images',
                'nodeVersion' => 2,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedNodeTypes(): array
    {
        return ['doc', 'paragraph', 'heading', 'text', 'hardBreak', 'image', 'productSpotlight', 'workshopStory', 'cta', 'gallery'];
    }

    /**
     * @return list<string>
     */
    public function allowedMarkTypes(): array
    {
        return ['bold', 'italic', 'link'];
    }

    /**
     * @return list<string>
     */
    public function processStages(): array
    {
        return ['selection', 'seasoning', 'joinery', 'carving', 'finishing', 'assembly'];
    }
}
