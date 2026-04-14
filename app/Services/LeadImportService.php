<?php

namespace App\Services;

use App\Core\App;

class LeadImportService
{
    public const PREVIEW_ROWS = 30;
    public const MAX_ROWS = 5000;

    public static function spreadsheetAvailable(): bool
    {
        return class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);
    }

    /**
     * @return array{headers: string[], rows: array<int, array<int, string|null>>, total_data_rows: int}
     */
    public static function loadSpreadsheet(string $path): array
    {
        if (!self::spreadsheetAvailable()) {
            throw new \RuntimeException('Biblioteca PhpSpreadsheet nao instalada. Execute: composer install');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);

        if ($raw === [] || $raw === [[]]) {
            return ['headers' => [], 'rows' => [], 'total_data_rows' => 0];
        }

        $headers = array_shift($raw);
        $headers = array_map(function ($h) {
            return strtolower(trim((string) ($h === null ? '' : $h)));
        }, is_array($headers) ? $headers : []);

        $rows = [];
        foreach ($raw as $line) {
            if (!is_array($line)) {
                continue;
            }
            $row = [];
            foreach ($line as $cell) {
                if ($cell === null) {
                    $row[] = '';
                } elseif (is_numeric($cell)) {
                    $row[] = (string) $cell;
                } else {
                    $row[] = trim((string) $cell);
                }
            }
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $rows[] = array_slice($row, 0, count($headers));
        }

        $rows = self::trimTrailingEmptyRows($rows);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total_data_rows' => count($rows),
        ];
    }

    /**
     * @return array{headers: string[], rows: array<int, array<int, string>>, total_data_rows: int, delimiter: string}
     */
    public static function loadCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new \RuntimeException('Nao foi possivel abrir o CSV');
        }

        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            return ['headers' => [], 'rows' => [], 'total_data_rows' => 0, 'delimiter' => ','];
        }

        $delimiter = self::detectCsvDelimiter($first);
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);
            return ['headers' => [], 'rows' => [], 'total_data_rows' => 0, 'delimiter' => $delimiter];
        }

        $headers = array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $headers);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }
            $rows[] = array_map(fn ($c) => trim((string) $c), $row);
        }

        fclose($handle);
        $rows = self::trimTrailingEmptyRows($rows);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total_data_rows' => count($rows),
            'delimiter' => $delimiter,
        ];
    }

    private static function detectCsvDelimiter(string $firstLine): string
    {
        $comma = substr_count($firstLine, ',');
        $semi = substr_count($firstLine, ';');
        $tab = substr_count($firstLine, "\t");

        if ($semi >= $comma && $semi >= $tab && $semi > 0) {
            return ';';
        }
        if ($tab >= $comma && $tab >= $semi && $tab > 0) {
            return "\t";
        }

        return ',';
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @return array<int, array<int, string>>
     */
    private static function trimTrailingEmptyRows(array $rows): array
    {
        while ($rows !== []) {
            $last = $rows[array_key_last($rows)];
            $nonEmpty = false;
            foreach ($last as $c) {
                if (trim((string) $c) !== '') {
                    $nonEmpty = true;
                    break;
                }
            }
            if ($nonEmpty) {
                break;
            }
            array_pop($rows);
        }

        return $rows;
    }

    public static function extensionFromName(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $ext;
    }

    public static function normalizeHeadersForMatch(array $headers): array
    {
        return array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $headers);
    }

    /**
     * Divide texto de produto em varias tags (varios produtos no mesmo lead).
     * Separadores: virgula, ponto-e-virgula, pipe ou quebra de linha (celulas Excel com Alt+Enter).
     *
     * @return string[]
     */
    public static function productStringToTagNames(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split('/[,;|\n]+/u', $text) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $t = trim($p);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }

    public static function importsStorageDir(): string
    {
        $base = dirname(__DIR__, 2) . '/storage/imports';
        if (!is_dir($base)) {
            mkdir($base, 0755, true);
        }

        return $base;
    }

    public static function log(string $message): void
    {
        App::log($message);
    }
}
