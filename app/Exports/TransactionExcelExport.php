<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class TransactionExcelExport implements FromCollection, WithHeadings, WithMapping, Responsable, WithColumnWidths
{
    use Exportable;

    public function __construct(protected Enumerable $data)
    {
        $this->fileName =  'data-transaksi-member-' . now()->format('Y-m-d') . '.xlsx';
    }

    public function collection(): Enumerable
    {
        return $this->data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
            'C' => 50,
            'D' => 20,
            'E' => 20,
            'F' => 20,
        ];
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Cabang',
            'Member',
            'Masuk/Keluar',
            'Kondisi',
            'Jumlah Tabung',
        ];
    }

    public function map($data): array
    {
        return [
            $data->date,
            $data->site_name,
            $data->member_code . ' - ' . $data->member_name,
            match ($data->transaction_type) {
                'in'     => 'Masuk',
                'out'    => 'Keluar',
                'return' => 'Retur',
                'sell'   => 'Jual',
                default  => '-',
            },
            match ($data->tube_status) {
                'filled'     => 'Isi',
                'empty'    => 'Kosong',
                'broken' => 'Rusak',
                'expired' => 'Afkir',
                'display'   => 'Pajangan',
                default  => '-',
            },
            $data->transaction_items_count
        ];
    }
}
