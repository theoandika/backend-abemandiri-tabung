<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class TubeExcelExport implements FromCollection, WithHeadings, WithMapping, Responsable, WithColumnWidths
{
    use Exportable;

    public function __construct(protected Enumerable $data)
    {
        $this->fileName =  'data-tabung-' . now()->format('Y-m-d') . '.xlsx';
    }

    public function collection(): Enumerable
    {
        return $this->data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 20,
            'C' => 25,
            'D' => 20,
            'E' => 30,
            'F' => 20,
            'G' => 30,
        ];
    }

    public function headings(): array
    {
        return [
            'Nomor',
            'Barcode',
            'Isi',
            'Jenis',
            'Tabung DM',
            'Aktif',
            'Posisi',
        ];
    }

    public function map($tube): array
    {
        return [
            $tube->number,
            $tube->latestTubeBarcode?->barcode,
            $tube->latestTubeContent?->tubeContentType?->name,
            $tube->type === 'medical' ? 'Medis' : 'Industri',
            $tube->own ? "Tabung DM" : "Bukan Tabung DM",
            $tube->active ? "Aktif" : "Tidak Aktif",
            $tube->statusNaming
        ];
    }
}
