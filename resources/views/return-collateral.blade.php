<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>SURAT TANDA TERIMA</title>
    <style>
        @page { margin: 30px; }
        body {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        .header {
            margin-bottom: 14px;
        }
        .title {
            margin-bottom: 8px;
        }
        .w-50 {
            width: 50%;
        }
        .w-40 {
            width: 40%;
        }
        .w-25 {
            width: 25%;
        }
        .w-30 {
            width: 30%;
        }
        .w-100 {
            width: 100%;
        }
        .bold {
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .mb-5 {
            margin-bottom: 5px;
        }
        .mb-20 {
            margin-bottom: 20px;
        }
        .border {
            border: 1px solid #000;
        }
        .table, .table td, .table th {
            border-collapse: collapse;
        }
        .table td, .table th {
            padding: 5px;
        }
        ol {
            margin-top: 0;
        }
        .h-60px {
            height: 60px;
            vertical-align: text-top;
        }
        hr {
            border: none;
            height: 1px;
            background-color: #000;
        }
    </style>
</head>
<body>
    <div class="w-100">
        <div class="header">
            <div class="title bold text-center">TANDA TERIMA PENGEMBALIAN TABUNG {{ strtoupper($data->site->name) }}</div>
            <table class="w-100">
                <tr>
                    <td class="w-50"><strong>No. Tanda Terima :</strong> {{ $data->document_number }}</td>
                    <td class="w-50 text-right"><strong>Tanggal :</strong> {{ $data->created_at->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>
        <div class="mb-5">Yang bertanda tangan di bawah ini:</div>
        <table class="table w-100 mb-5">
            <tr>
                <th class="text-left w-30">Nama</th>
                <td class="border">{{ $data->member_name }}</td>
            </tr>
            <tr>
                <th class="text-left w-30">Alamat</th>
                <td class="border">{{ $data->member_address }} -- {{ $data->contact_person }}</td>
            </tr>
        </table>
            <div class="mb-5">Sebagai PEMILIK dari:</div>
        <table class="table w-100 mb-5">
            <tr>
                <th class="text-left w-30">Nama Usaha</th>
                <td class="border">{{ $data->company_name }} -- {{ $data->member->code }} | {{ $data->member_name }}</td>
            </tr>
        </table>
        <div class="mb-5">Telah mengembalikan pinjaman tabung dengan rincian sebagai berikut:</div>
        <table class="table w-100 mb-5 border">
            <tr>
                <th class="border">JENIS TABUNG</th>
                <th class="border">KONDISI KLEP</th>
                <th class="border">TUTUP TABUNG</th>
                <th class="border">QTY PINJAM</th>
                <th class="border">JAMINAN</th>
                <th class="border">TOTAL JAMINAN</th>
            </tr>
            @foreach ($data->collateralItems as $item)
            <tr>
                <td class="border text-center">{{ strtoupper($item->tubeContentType->name) }}</td>
                <td class="border text-center">{{ $item->klep_condition }}</td>
                <td class="border text-center">{{ $item->tube_cap }}</td>
                <td class="border text-center">{{ $item->tube_quantity }}</td>
                <td class="border text-center">Rp{{ number_format($item->nominal, 0, ',', '.') }}</td>
                <td class="border text-center">Rp{{ number_format($item->total_amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </table>
        <div class="mb-20">Telah menerima pengembalian jaminan via : <strong>{{ $data->return_payment_method }}</strong></div>
        <table class="w-100 mb-20">
            <tr>
                <td class="w-50 text-center">Peminjam,</td>
                <td class="w-50 text-center">Dunia Motor,</td>
            </tr>
            <tr>
                <td class="w-50 h-60px"></td>
                <td class="w-50 h-60px"></td>
            </tr>
            <tr>
                <td class="w-50 bold text-center"><hr class="w-50">{{ $data->member_name }}</td>
                <td class="w-50 bold text-center"><hr class="w-50">{{ $data->pic }}</td>
            </tr>
        </table>
        <div><i>Lembar 1: customer, Lembar 2 & 3: arsip DM</i></div>
    </div>
</body>
</html>