<?php

namespace App\Services\Seo;

use App\Tenancy\TenantContext;
use Closure;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetExport
{
    /**
     * @param  list<string>  $headings
     * @param  Closure(): iterable<array>  $rows
     */
    public function download(string $filename, array $headings, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(app(TenantContext::class)->wrap(function () use ($headings, $rows): void {
            $book = new Spreadsheet;
            try {
                $sheet = $book->getActiveSheet();
                $write = function (array $cells, int $row) use ($sheet): void {
                    foreach (array_values($cells) as $column => $value) {
                        $sheet->setCellValueExplicit([$column + 1, $row], (string) ($value ?? ''), DataType::TYPE_STRING);
                    }
                };
                $write($headings, 1);
                $row = 2;
                foreach ($rows() as $cells) {
                    $write($cells, $row++);
                }
                $sheet->freezePane('A2');
                $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
                $sheet->getStyle('1:1')->getFont()->setBold(true);
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }), $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
