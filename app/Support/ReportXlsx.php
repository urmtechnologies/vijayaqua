<?php

namespace App\Support;

final class ReportXlsx
{
    // A small, dependency-free OOXML workbook. Text always uses inline strings,
    // so a party name beginning with "=" is never interpreted as a formula.
    public static function make(array $headings, array $rows): string
    {
        $last = self::column(count($headings));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="16"/><cols>';
        foreach ($headings as $index => $heading) {
            $column = $index + 1;
            $xml .= '<col min="'.$column.'" max="'.$column.'" width="'.(str_contains($heading, 'Note') ? '42' : '24').'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $allRows = array_merge([$headings], $rows);
        foreach ($allRows as $index => $values) {
            $number = $index + 1;
            $xml .= '<row r="'.$number.'">';
            foreach ($values as $column => $value) {
                $cell = self::column($column + 1).$number;
                $numeric = $number > 1 && self::numericColumn($headings[$column])
                    && is_scalar($value) && preg_match('/^-?[0-9,]+(?:\.[0-9]+)?$/', (string) $value);
                if ($numeric) {
                    $xml .= '<c r="'.$cell.'"><v>'.str_replace(',', '', (string) $value).'</v></c>';
                } else {
                    $safe = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) ($value ?? ''));
                    $string = htmlspecialchars($safe, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xml .= '<c r="'.$cell.'" t="inlineStr"'.($number === 1 ? ' s="1"' : '').'><is><t xml:space="preserve">'.$string.'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData><autoFilter ref="A1:'.$last.max(1, count($allRows)).'"/></worksheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
                .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
                .'<fill><patternFill patternType="solid"><fgColor rgb="FF16324F"/><bgColor indexed="64"/></patternFill></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
                .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => $xml,
        ];
        return self::zip($files);
    }

    private static function numericColumn(string $heading): bool
    {
        return str_contains($heading, '(Rs)') || str_contains($heading, 'CTN')
            || in_array($heading, ['Cartons', 'Hours'], true);
    }

    private static function column(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $name = chr(65 + ($number - 1) % 26).$name;
            $number = intdiv($number - 1, 26);
        }
        return $name;
    }

    private static function zip(array $files): string
    {
        $local = $directory = '';
        foreach ($files as $name => $content) {
            $length = strlen($content);
            $crc = crc32($content);
            $offset = strlen($local);
            $local .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $length, $length, strlen($name), 0)
                .$name.$content;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0,
                $crc, $length, $length, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }
        return $local.$directory.pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files),
            strlen($directory), strlen($local), 0);
    }
}
