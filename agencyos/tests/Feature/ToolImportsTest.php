<?php

namespace Tests\Feature;

use App\Services\Seo\TableFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ToolImportsTest extends TestCase
{
    public function test_xlsx_table_preserves_supplied_strings_without_evaluating_formulas(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['source_url', 'target_url', 'anchor'], ['https://source.example.com/', 'https://example.com/', '=SUM(1,2)']], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'table');
        (new Xlsx($book))->save($path);
        try {
            $rows = app(TableFile::class)->read(new UploadedFile($path, 'backlinks.xlsx', null, null, true), ['source_url', 'target_url'], ['source_url', 'target_url', 'anchor', 'campaign']);
            $this->assertCount(1, $rows);
            $this->assertSame('=SUM(1,2)', $rows[0]['anchor']);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_duplicate_headers_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(TableFile::class)->read(UploadedFile::fake()->createWithContent('rows.csv', "source_url,source_url\nhttps://example.com/,https://example.com/"), ['source_url'], ['source_url']);
    }
}
