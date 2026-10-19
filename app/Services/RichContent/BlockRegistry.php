<?php

namespace App\Services\RichContent;

class BlockRegistry
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return list<array{type: string, name: string, description: string, group: string, icon: string}>
     */
    public function blocks(): array
    {
        return [
            ['type' => 'paragraph', 'name' => 'Paragraph', 'description' => 'Body text for the article.', 'group' => 'Writing', 'icon' => 'fa-solid fa-paragraph'],
            ['type' => 'heading', 'name' => 'Heading', 'description' => 'Section heading. Body headings are H2 and H3 only.', 'group' => 'Writing', 'icon' => 'fa-solid fa-heading'],
            ['type' => 'image', 'name' => 'Image', 'description' => 'A single photograph with context-specific alt text.', 'group' => 'Media', 'icon' => 'fa-solid fa-image'],
            ['type' => 'gallery', 'name' => 'Gallery', 'description' => 'Several photographs in a grid or a carousel.', 'group' => 'Media', 'icon' => 'fa-solid fa-images'],
            ['type' => 'productSpotlight', 'name' => 'Product Spotlight', 'description' => 'A live stone card resolved from the catalogue.', 'group' => 'Catalogue', 'icon' => 'fa-solid fa-gem'],
            ['type' => 'workshopStory', 'name' => 'Quarry Story', 'description' => 'Process photography and notes from selection to packing.', 'group' => 'Craft', 'icon' => 'fa-solid fa-hammer'],
            ['type' => 'cta', 'name' => 'Call to Action', 'description' => 'A heading, supporting line, and a single safe link.', 'group' => 'Close', 'icon' => 'fa-solid fa-arrow-right'],
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
        return ['selection', 'cutting', 'finishing', 'packing'];
    }
}
