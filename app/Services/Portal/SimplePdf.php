<?php

namespace App\Services\Portal;

class SimplePdf
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public function render(string $title, array $headings, array $rows): string
    {
        $lines = [
            $this->plain($title),
            'Royal Indian Stones staff report',
            'Generated '.$this->plain(now()->timezone('Asia/Kolkata')->format('d M Y H:i')).' IST',
            '',
            $this->plain(implode(' | ', $headings)),
        ];

        if ($rows === []) {
            $lines[] = 'No records in this range.';
        }

        foreach ($rows as $row) {
            $lines[] = $this->plain(implode(' | ', $row));
        }

        $lines[] = '';
        $lines[] = 'Private staff record. Do not publish this file.';

        return $this->document($lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function document(array $lines): string
    {
        $chunks = array_chunk($lines, 42);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageIds = [];
        $next = 4;

        foreach ($chunks as $chunk) {
            $stream = "BT /F1 9 Tf 40 760 Td 14 TL\n";
            foreach ($chunk as $line) {
                $stream .= '('.$this->escape($line).") Tj T*\n";
            }
            $stream .= 'ET';
            $contentId = $next++;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $pageId = $next++;
            $pageIds[] = $pageId;
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentId} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
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
        $count = $max + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer << /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function plain(string $value): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        return substr($clean, 0, 110);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->plain($value));
    }
}
