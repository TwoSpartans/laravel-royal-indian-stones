<?php

namespace App\Services\Portal;

class SimplePdf
{
    /**
     * @param  list<array{title: string, headings: list<string>, rows: list<list<string>>}>  $sections
     */
    public function render(string $title, array $sections, string $note = ''): string
    {
        $pages = [];
        $ops = '';
        $y = 560.0;

        $flush = function () use (&$ops, &$pages, &$y): void {
            if ($ops === '') {
                return;
            }
            $pages[] = $ops;
            $ops = '';
            $y = 560.0;
        };

        $need = function (float $height) use (&$y, $flush): void {
            if ($y - $height < 36) {
                $flush();
            }
        };

        $text = function (string $value, float $x, float $size, bool $bold) use (&$ops, &$y): void {
            $font = $bold ? 'F2' : 'F1';
            $ops .= sprintf(
                "BT /%s %.1f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $font,
                $size,
                $x,
                $y,
                $this->escape($value),
            );
        };

        $need(28);
        $text($title, 36, 14, true);
        $y -= 18;
        $text('Royal Indian Stones staff report', 36, 9, false);
        $y -= 14;
        $text('Generated '.now()->timezone('Asia/Kolkata')->format('d M Y H:i').' IST', 36, 9, false);
        $y -= 16;
        if ($note !== '') {
            $need(14);
            $text($note, 36, 9, false);
            $y -= 16;
        }

        foreach ($sections as $section) {
            $need(36);
            if ($section['title'] !== '') {
                $text($section['title'], 36, 12, true);
                $y -= 16;
            }
            $widths = $this->widths($section['headings'], $section['rows'], 720);
            $drawRow = function (array $cells, bool $bold) use (&$y, $need, $text, $widths): void {
                $need(14);
                $x = 36.0;
                foreach ($widths as $index => $width) {
                    $text($this->fit((string) ($cells[$index] ?? ''), $width), $x, 8, $bold);
                    $x += $width;
                }
                $y -= 13;
            };
            $drawRow($section['headings'], true);
            if ($section['rows'] === []) {
                $need(14);
                $text('No records in this range.', 36, 8, false);
                $y -= 14;
            }
            foreach ($section['rows'] as $row) {
                if ($y - 13 < 36) {
                    $flush();
                    $drawRow($section['headings'], true);
                }
                $drawRow($row, false);
            }
            $y -= 8;
        }

        $need(16);
        $text('Private staff record. Do not publish this file.', 36, 8, false);
        $flush();

        return $this->document($pages);
    }

    /**
     * @param  list<string>  $streams
     */
    private function document(array $streams): string
    {
        if ($streams === []) {
            $streams = ['BT /F1 9 Tf 36 560 Td (Empty report) Tj ET'];
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageIds = [];
        $next = 5;
        foreach ($streams as $stream) {
            $contentId = $next++;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $pageId = $next++;
            $pageIds[] = $pageId;
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Contents {$contentId} 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>";
        }

        $kids = implode(' ', array_map(fn (int $id): string => $id.' 0 R', $pageIds));
        $objects[2] = '<< /Type /Pages /Count '.count($pageIds).' /Kids ['.$kids.'] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        $max = max(array_keys($objects));
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".($max + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= 'trailer << /Size '.($max + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     * @return list<float>
     */
    private function widths(array $headings, array $rows, float $total): array
    {
        $scores = [];
        foreach ($headings as $index => $heading) {
            $scores[$index] = max(8, min(32, strlen($heading)));
        }
        foreach (array_slice($rows, 0, 30) as $row) {
            foreach ($row as $index => $cell) {
                $scores[$index] = max($scores[$index] ?? 8, min(32, strlen((string) $cell)));
            }
        }
        $sum = array_sum($scores) ?: 1;

        return array_map(fn (int $score): float => $total * $score / $sum, $scores);
    }

    private function fit(string $value, float $width): string
    {
        $text = $this->plain($value);
        $max = max(1, (int) floor($width / 4.4));
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, max(1, $max - 1)).'.';
    }

    private function plain(string $value): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->plain($value));
    }
}
