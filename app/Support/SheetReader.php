<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/** Reads the first sheet of a CSV or .xlsx file into rows of strings. No extra libraries needed. */
class SheetReader
{
    public static function read(string $path, string $ext): array
    {
        return match ($ext) {
            'csv', 'txt' => self::csv($path),
            'xlsx' => self::xlsx($path),
            'xls' => throw new RuntimeException("Old .xls files can't be read. Open it in Excel and save as .xlsx or CSV, then upload again."),
            default => throw new RuntimeException('Upload a CSV or Excel (.xlsx) file.'),
        };
    }

    private static function csv(string $path): array
    {
        $text = file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($text, "\n");
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        $rows = [];
        while (($row = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            if (array_filter($row, fn ($c) => trim((string) $c) !== '')) {
                $rows[] = array_map(fn ($c) => trim((string) $c), $row);
            }
        }
        return $rows;
    }

    private static function xlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('This server cannot open Excel files. Save the file as CSV and upload that instead.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open this Excel file. Try saving it again as .xlsx or CSV.');
        }
        $xml = fn ($name) => ($s = $zip->getFromName($name)) === false ? null : simplexml_load_string($s);

        $shared = [];
        if ($ss = $xml('xl/sharedStrings.xml')) {
            foreach ($ss->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }

        // Which cell styles are dates, so serial numbers become YYYY-MM-DD.
        $dateStyles = [];
        if ($st = $xml('xl/styles.xml')) {
            $custom = [];
            foreach ($st->numFmts->numFmt ?? [] as $f) {
                $code = preg_replace('/"[^"]*"|\[[^\]]*\]/', '', strtolower((string) $f['formatCode']));
                $custom[(int) $f['numFmtId']] = preg_match('/[dy]/', $code) === 1;
            }
            $i = 0;
            foreach ($st->cellXfs->xf ?? [] as $xf) {
                $id = (int) $xf['numFmtId'];
                $dateStyles[$i++] = ($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || ($custom[$id] ?? false);
            }
        }

        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $xml('xl/workbook.xml');
        $rels = $xml('xl/_rels/workbook.xml.rels');
        if ($wb && $rels && isset($wb->sheets->sheet[0])) {
            $rid = (string) $wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');
                    $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }
        $sheet = $xml($sheetPath);
        $zip->close();
        if (! $sheet) {
            throw new RuntimeException('That Excel file has no sheet to read.');
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $out = [];
            foreach ($row->c as $c) {
                $col = self::colIndex(preg_replace('/\d/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $val = (string) $c->v;
                if ($type === 's') {
                    $val = $shared[(int) $val] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string) ($c->is->t ?? '');
                } elseif ($type === 'b') {
                    $val = $val === '1' ? 'TRUE' : 'FALSE';
                } elseif ($val !== '' && is_numeric($val)) {
                    if ($dateStyles[(int) $c['s']] ?? false) {
                        $val = gmdate('Y-m-d', (int) round(((float) $val - 25569) * 86400));
                    } elseif (floor((float) $val) == (float) $val && abs((float) $val) < 1e15) {
                        $val = number_format((float) $val, 0, '.', ''); // mobile numbers stored as numbers
                    }
                }
                $out[$col] = trim($val);
            }
            if (array_filter($out, fn ($v) => $v !== '')) {
                $max = max(array_keys($out));
                $rows[] = array_map(fn ($i) => $out[$i] ?? '', range(0, $max));
            }
        }
        return $rows;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}
