<?php
namespace App\Services\Seo;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
class KeywordFile
{
    public function read(UploadedFile $file): string
    {
        $rows=[];
        if(strtolower($file->getClientOriginalExtension())==='csv') {
            $handle=fopen($file->getRealPath(),'r');
            try { while(($row=fgetcsv($handle,65536,',','"',''))!==false) { if(count($rows)>=501) { throw ValidationException::withMessages(['file'=>'Import at most 500 keywords.']); }$rows[]=$row[0] ?? ''; } }
            finally { fclose($handle); }
        } else {
            $zip=new \ZipArchive;
            if($zip->open($file->getRealPath())!==true) { throw ValidationException::withMessages(['file'=>'Invalid XLSX archive.']); }
            try { $size=0;for($i=0;$i<$zip->numFiles;$i++) { $size+=($zip->statIndex($i)['size'] ?? 0);if($size>2097152 || $zip->numFiles>100) { throw ValidationException::withMessages(['file'=>'Workbook exceeds the safe expanded size limit.']); } } }
            finally { $zip->close(); }
            try {
                $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx;$reader->setReadDataOnly(true);
                $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                    public function readCell(string $columnAddress,int $row,string $worksheetName=''): bool { return $columnAddress==='A' && $row<=502; }
                });
                $book=$reader->load($file->getRealPath());$sheet=$book->getSheet(0);
                for($i=1;$i<=$sheet->getHighestDataRow();$i++) { $rows[]=(string)$sheet->getCell('A'.$i)->getValue(); }
                $book->disconnectWorksheets();
            } catch(\Throwable) { throw ValidationException::withMessages(['file'=>'Unable to read workbook. Use a plain keyword column in the first worksheet.']); }
        }
        if(mb_strtolower(ltrim($rows[0] ?? '',"\xEF\xBB\xBF"))==='keyword') { array_shift($rows); }
        $rows=array_filter($rows,fn ($row)=>trim($row)!=='');
        if(!$rows || count($rows)>500) { throw ValidationException::withMessages(['file'=>'Supply between 1 and 500 keyword rows.']); }
        return implode("\n",$rows);
    }
}
