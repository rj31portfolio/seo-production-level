<?php

namespace App\Services\Seo;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class TableFile
{
    public function read(UploadedFile $file, array $required, array $allowed): array
    {
        $rows = [];
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            try {
                while (($cells = fgetcsv($handle, 65536, ',', '"', '')) !== false) {
                    $rows[] = $cells;
                    if (count($rows) > 501) {
                        throw ValidationException::withMessages(['file' => 'Import at most 500 rows.']);
                    }
                }
            } finally {
                fclose($handle);
            }
        } else {
            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                throw ValidationException::withMessages(['file' => 'Invalid XLSX archive.']);
            }
            try {
                $size = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $size += ($zip->statIndex($i)['size'] ?? 0);
                    if ($size > 2097152 || $zip->numFiles > 100) {
                        throw ValidationException::withMessages(['file' => 'Workbook exceeds the safe expanded size limit.']);
                    }
                }
            } finally {
                $zip->close();
            }
            try {
                $reader = new Xlsx;
                $reader->setReadDataOnly(true);
                $reader->setReadFilter(new class implements IReadFilter
                {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                    {
                        return Coordinate::columnIndexFromString($columnAddress) <= 10 && $row <= 502;
                    }
                });
                $book = $reader->load($file->getRealPath());
                $sheet = $book->getSheet(0);
                $columns = min(10, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
                for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
                    $cells = [];
                    for ($column = 1; $column <= $columns; $column++) {
                        $cell = $sheet->getCell([$column, $row]);
                        $cells[] = $cell->getValue() === null ? '' : (string) $cell->getValue();
                    }$rows[] = $cells;
                }$book->disconnectWorksheets();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['file' => 'Unable to read workbook. Use plain values in the first worksheet; dates must be YYYY-MM-DD text.']);
            }
        }
        $header = array_map(fn ($value) => trim(ltrim((string) $value, "\xEF\xBB\xBF")), array_shift($rows) ?? []);
        if (! $header || array_diff($required, $header) || array_diff($header, $allowed) || count(array_unique($header)) !== count($header)) {
            throw ValidationException::withMessages(['file' => 'Use unique permitted headers: '.implode(', ', $allowed).'. Required: '.implode(', ', $required).'.']);
        }
        if (! $rows || count($rows) > 500) {
            throw ValidationException::withMessages(['file' => 'Import between 1 and 500 complete rows.']);
        }
        $mapped = [];
        foreach ($rows as $cells) {
            if (count($cells) !== count($header)) {
                throw ValidationException::withMessages(['file' => 'Every row must contain the same number of columns as its header.']);
            }$mapped[] = array_combine($header, $cells);
        }

return $mapped;
    }
}
