<?php

namespace App\Support;

use App\Models\Post;

final class ReadingTime
{
    public const WORDS_PER_MINUTE = 200;

    public static function minutesFor(Post $post): int
    {
        $summary = trim((string) $post->summary);
        if ($post->content_source === 'studio') {
            $text = trim($summary.' '.self::documentText($post->body_document));
        } else {
            $text = trim($summary.' '.html_entity_decode(strip_tags((string) $post->body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $words = str_word_count($text);
        if ($words < 1) {
            return 1;
        }

        return (int) max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    private static function documentText(mixed $node): string
    {
        if (! is_array($node)) {
            return '';
        }

        $text = '';
        if (isset($node['text']) && is_string($node['text'])) {
            $text .= ' '.$node['text'];
        }

        foreach ($node as $key => $value) {
            if ($key === 'text' || ! is_array($value)) {
                continue;
            }
            $text .= ' '.self::documentText($value);
        }

        return $text;
    }
}
