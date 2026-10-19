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
            $this->make('blank', 'Blank article', 'One empty paragraph, for a story that does not follow a set shape.', [
                $this->paragraph(''),
            ]),
            $this->make('buying-guide', 'Buying guide', 'Colour, finish, thickness, and a stone from the catalogue.', $this->buyingGuide()),
            $this->make('quarry-story', 'Quarry story', 'Selection, the work after the block leaves the bed, and a gallery.', $this->quarryStory()),
            $this->make('stone-story', 'Stone story', 'Origin, finish, and one live stone card.', $this->stoneStory()),
            $this->make('care-guide', 'Care guide', 'Cleaning, sealing, and what to keep off the surface.', $this->careGuide()),
            $this->make('room-guide', 'Room guide', 'Floor, wall, or courtyard, with a gallery and a catalogue link.', $this->roomGuide()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buyingGuide(): array
    {
        return [
            $this->heading('What to look for before you order'),
            $this->paragraph('Name the colour range, the amount of veining, and the finish you are writing about. A buyer should be able to tell this stone from the next one in the catalogue after the first paragraph.'),
            $this->heading('Finish and thickness', 3),
            $this->paragraph('Say whether the face is honed, polished, flamed, or natural. Then say the thickness you recommend for a floor, and the lighter thickness that is enough for a wall or a step riser.'),
            $this->heading('Where the stone belongs', 3),
            $this->paragraph('Separate indoor floors, wet rooms, courtyards, and cladding. Note slip underfoot, how the colour changes in sun, and whether the edge should be eased.'),
            $this->spotlight('From the catalogue', 'Choose the stone this guide is about. The card uses the live catalogue name and photograph, so the article does not store a copied price.'),
            $this->cta('See the stones', 'Open the catalogue and compare the finish and colour named above.', 'Browse the catalogue', '/products'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quarryStory(): array
    {
        return [
            $this->heading('From the bed to the crate'),
            $this->paragraph('Start with the place. Name the region, the colour of the bed, and why this block was kept with the others rather than mixed into a later lot.'),
            $this->workshop(
                'At selection',
                'selection',
                'Describe how the block is read: colour, cracks, and the face that should stay together. Add a photograph of the bed or the marked block.',
            ),
            $this->heading('After the block leaves the quarry', 3),
            $this->paragraph('Follow the same lot through cutting, finishing, and packing. Keep the batch together so the colour in the room matches the colour that left the bed.'),
            $this->gallery(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stoneStory(): array
    {
        return [
            $this->heading('The character of the stone'),
            $this->paragraph('Describe the ground colour, the marks that repeat, and the region it comes from. Avoid a list of every finish the yard can offer.'),
            $this->heading('How the face is finished', 3),
            $this->paragraph('Explain what the finish does to the colour and to the hand. A hone keeps the grain quiet. A polish deepens it. A natural face keeps the split.'),
            $this->image('Add a photograph of the face, close enough to show the grain.'),
            $this->spotlight('The stone', 'Choose the catalogue stone this story follows. Write the summary as a reason to look at it, not as a specification sheet.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function careGuide(): array
    {
        return [
            $this->heading('Everyday care'),
            $this->paragraph('Dust or sweep loose grit before it is walked across the face. Wash with clean water and a mild, pH-neutral cleaner. Dry a polished floor so water does not sit in the joints.'),
            $this->heading('Sealing', 3),
            $this->paragraph('Outdoor sandstone and a honed marble in a wet room need a sealer suited to that stone. Say how often to renew it, and say when a dense polished granite can stay unsealed.'),
            $this->heading('What to keep off the surface', 3),
            $this->paragraph('Keep acids, vinegar, lemon, and harsh bathroom cleaners off marble and limestone. Wipe oil and wine while they are still wet. Do not use a wire pad on a honed or polished face.'),
            $this->cta('Match the care to the stone', 'The right sealer depends on the stone. Find it in the catalogue and read its finish before you treat the floor.', 'Open the catalogue', '/products'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roomGuide(): array
    {
        return [
            $this->heading('Choose the stone for the room'),
            $this->paragraph('Decide the room before the colour. A floor, a wall, and a courtyard ask for different thickness, finish, and edge.'),
            $this->heading('On the floor', 3),
            $this->paragraph('Use a thickness that can take the traffic, and a finish with enough grip for the room. Plan the joint width and the border before the first slab is cut.'),
            $this->heading('On a wall or in a courtyard', 3),
            $this->paragraph('Cladding can be lighter than paving. In a courtyard, name the finish that stays comfortable in sun and the sealer that keeps water from marking the face.'),
            $this->gallery(),
            $this->spotlight('A stone for this room', 'Choose one stone that suits the use above. Set the card to stacked if the photograph should sit over the note.', 'image-left'),
            $this->cta('Plan the room from the catalogue', 'Compare sizes and finishes, then ask for the lot that matches the sample in this article.', 'View the catalogue', '/products'),
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

    /**
     * @param  array<string, string>  $frame
     * @return array<string, mixed>
     */
    private function paragraph(string $text, array $frame = []): array
    {
        return [
            'type' => 'paragraph',
            'attrs' => ['frame' => $frame],
            'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function heading(string $text, int $level = 2): array
    {
        return [
            'type' => 'heading',
            'attrs' => ['level' => $level, 'frame' => ['width' => 'text']],
            'content' => [['type' => 'text', 'text' => $text]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function image(string $caption): array
    {
        return [
            'type' => 'image',
            'attrs' => [
                'mediaId' => null,
                'alt' => '',
                'decorative' => false,
                'caption' => $caption,
                'credit' => '',
                'frame' => ['width' => 'wide', 'ratio' => '3:2'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gallery(): array
    {
        return [
            'type' => 'gallery',
            'attrs' => [
                'mode' => 'grid',
                'items' => [],
                'frame' => ['width' => 'wide'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spotlight(string $eyebrow, string $summary, string $layout = 'image-left'): array
    {
        return [
            'type' => 'productSpotlight',
            'attrs' => [
                'productId' => null,
                'presentation' => [
                    'layout' => $layout,
                    'ctaLabel' => 'View stone',
                ],
                'editorial' => [
                    'eyebrow' => $eyebrow,
                    'summary' => $summary,
                ],
                'frame' => ['width' => 'wide', 'background' => 'ivory', 'spacing' => 'relaxed'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workshop(string $title, string $stage, string $body): array
    {
        return [
            'type' => 'workshopStory',
            'attrs' => [
                'title' => $title,
                'processStage' => $stage,
                'body' => $body,
                'mediaIds' => [],
                'caption' => '',
                'frame' => ['width' => 'wide', 'background' => 'sand'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cta(string $title, string $body, string $buttonText, string $buttonUrl): array
    {
        return [
            'type' => 'cta',
            'attrs' => [
                'title' => $title,
                'body' => $body,
                'buttonText' => $buttonText,
                'buttonUrl' => $buttonUrl,
                'frame' => ['width' => 'text', 'background' => 'forest', 'align' => 'left'],
            ],
        ];
    }
}
