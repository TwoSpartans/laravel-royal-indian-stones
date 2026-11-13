<?php

namespace App\Services\BlogStudio;

class ArticleTemplates
{
    /**
     * @return list<array{id: string, name: string, description: string, document: array<string, mixed>}>
     */
    public function all(): array
    {
        return [
            $this->make('blank', 'Blank Article', 'One empty paragraph to start writing.', [
                ['type' => 'paragraph'],
            ]),
            $this->make('buying-guide', 'Buying Guide', 'A heading, the buying advice, a product, and a closing link.', [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'What to look for']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Explain the wood, the joinery, and what the piece is for.']]],
                ['type' => 'productSpotlight'],
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'A piece that fits']]],
                ['type' => 'cta'],
            ]),
            $this->make('workshop-story', 'Workshop Story', 'A process heading and a workshop story block.', [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'In the workshop']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Where the piece starts, and who makes it.']]],
                ['type' => 'workshopStory'],
            ]),
            $this->make('product-story', 'Product Story', 'An introduction and one live product card.', [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'The piece']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Why this piece belongs in the story.']]],
                ['type' => 'productSpotlight'],
            ]),
            $this->make('care-guide', 'Care Guide', 'Care notes and a link back to the collection.', [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'How to care for it']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Oil, sunlight, and the seasonal check.']]],
                ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'What to avoid']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Keep standing water and harsh cleaners off the finish.']]],
                ['type' => 'cta'],
            ]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @return array{id: string, name: string, description: string, document: array<string, mixed>}
     */
    private function make(string $id, string $name, string $description, array $content): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'document' => [
                'type' => 'doc',
                'schemaVersion' => 1,
                'content' => $content,
            ],
        ];
    }
}
