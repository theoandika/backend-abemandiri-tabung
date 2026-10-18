<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class MemberExcelImport implements WithStartRow, WithHeadingRow, Import
{
    use Importable;

    public function startRow(): int
    {
        return 2;
    }
}