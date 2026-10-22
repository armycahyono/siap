<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SimpleXlsxGenerator
{
    /**
     * Generate an in-memory XLSX binary string.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, int>  $colWidths
     */
    public static function createBinary(string $sheetName, array $headers, array $rows, array $colWidths = []): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_gen_');

        $zip = new ZipArchive;
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // 1. [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        // 2. _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        // 3. xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // 4. xl/workbook.xml
        $safeSheetName = htmlspecialchars($sheetName, ENT_XML1, 'UTF-8');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="'.$safeSheetName.'" sheetId="1" r:id="rId1"/>'
            .'</sheets>'
            .'</workbook>');

        // 5. xl/styles.xml (Professional Tabler / Navy Theme)
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><name val="Calibri"/><sz val="11"/></font>'
            .'<font><b/><name val="Calibri"/><sz val="11"/><color rgb="FFFFFFFF"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF102D52"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFD0D5DD"/></left>'
            .'<right style="thin"><color rgb="FFD0D5DD"/></right>'
            .'<top style="thin"><color rgb="FFD0D5DD"/></top>'
            .'<bottom style="thin"><color rgb="FFD0D5DD"/></bottom>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            .'</cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'</cellXfs>'
            .'</styleSheet>');

        // 6. xl/worksheets/sheet1.xml
        $sheetDataXml = '';

        // Row 1: Header
        $sheetDataXml .= '<row r="1" ht="28" customHeight="1">';
        foreach ($headers as $cIdx => $h) {
            $col = self::getColLetter($cIdx);
            $safe = htmlspecialchars($h, ENT_XML1, 'UTF-8');
            $sheetDataXml .= '<c r="'.$col.'1" t="inlineStr" s="1"><is><t>'.$safe.'</t></is></c>';
        }
        $sheetDataXml .= '</row>';

        // Data rows
        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 2;
            $sheetDataXml .= '<row r="'.$rowNum.'" ht="22" customHeight="1">';
            foreach ($row as $cIdx => $val) {
                $col = self::getColLetter($cIdx);
                $safe = htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
                $sheetDataXml .= '<c r="'.$col.$rowNum.'" t="inlineStr" s="2"><is><t>'.$safe.'</t></is></c>';
            }
            $sheetDataXml .= '</row>';
        }

        $colsXml = '';
        if (! empty($colWidths)) {
            $colsXml = '<cols>';
            foreach ($colWidths as $idx => $w) {
                $cNum = $idx + 1;
                $colsXml .= '<col min="'.$cNum.'" max="'.$cNum.'" width="'.$w.'" customWidth="1"/>';
            }
            $colsXml .= '</cols>';
        }

        $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$colsXml
            .'<sheetData>'.$sheetDataXml.'</sheetData>'
            .'</worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();

        $content = (string) file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    /**
     * Stream an XLSX download response.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, int>  $colWidths
     */
    public static function download(string $filename, string $sheetName, array $headers, array $rows, array $colWidths = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheetName, $headers, $rows, $colWidths) {
            echo self::createBinary($sheetName, $headers, $rows, $colWidths);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Convert 0-based column index to Excel column letter (0 => A, 27 => AB, etc.).
     */
    public static function getColLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65).$letter;
            $index = intdiv($index, 26) - 1;
        }

        return $letter;
    }
}
