<?php

namespace App\Support;

final class ReportPdf
{
    public static function make(string $title, array $headings, array $rows, string $filters): string
    {
        $pageRows = array_chunk($rows, 23);
        if (! $pageRows) $pageRows = [[]];
        $pages = [];
        $width = 786 / count($headings);
        foreach ($pageRows as $pageIndex => $items) {
            $text = "0.09 0.19 0.30 rg\n".self::text(28, 560, 16, $title, true);
            $text .= "0.36 0.41 0.48 rg\n".self::text(28, 542, 8, $filters);
            $text .= "0.09 0.19 0.30 rg 28 504 786 26 re f\n1 1 1 rg\n";
            foreach ($headings as $j => $heading) {
                $text .= self::text(32 + $j * $width, 513, 8, self::fit($heading, $width, 8), true);
            }
            foreach ($items as $i => $row) {
                $y = 481 - $i * 19;
                if ($i % 2 === 0) $text .= "0.95 0.97 0.98 rg 28 ".($y - 5)." 786 19 re f\n";
                $text .= "0.14 0.21 0.29 rg\n";
                foreach ($row as $j => $value) {
                    $text .= self::text(32 + $j * $width, $y, 8, self::fit((string) ($value ?? ''), $width, 8));
                }
            }
            if (! $items) $text .= "0.3 0.35 0.4 rg\n".self::text(32, 479, 10, 'No records match these filters.');
            $text .= "0.4 0.45 0.5 rg\n".self::text(28, 29, 8, 'Vijay Aqua | Generated '.now()->format('d M Y, h:i A'))
                .self::text(750, 29, 8, ($pageIndex + 1).' / '.count($pageRows));
            $pages[] = $text;
        }
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($pages as $index => $content) {
            $pageId = 5 + $index * 2;
            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($pageId + 1).' 0 R >>';
            $objects[$pageId + 1] = '<< /Length '.strlen($content).' >>'."\nstream\n".$content."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($objects as $id => $_) $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private static function text(float $x, float $y, int $size, string $text, bool $bold = false): string
    {
        $encoded = self::encode($text);
        return sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, $x, $y, $encoded)."\n";
    }

    private static function fit(string $text, float $width, int $size): string
    {
        $limit = max(4, (int) floor(($width - 8) / ($size * 0.52)));
        if (function_exists('mb_strimwidth')) return mb_strimwidth($text, 0, $limit, '...', 'UTF-8');
        return strlen($text) > $limit ? substr($text, 0, $limit - 3).'...' : $text;
    }

    private static function encode(string $text, bool $escape = true): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $encoded = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) : $text;
        $encoded = preg_replace('/[\x00-\x1f\x7f]/', '', $encoded === false ? '' : $encoded);
        return $escape ? str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded) : $encoded;
    }
}
