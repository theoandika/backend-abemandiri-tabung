<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>SURAT JAMINAN</title>
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
            <div class="title bold text-center">SURAT JAMINAN {{ strtoupper($data->site->name) }}</div>
            <table class="w-100">
                <tr>
                    <td class="w-50"><strong>No. Surat Jaminan :</strong> {{ $data->document_number }}</td>
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
        <div class="mb-5">Sebagai DIRI SENDIRI - PRIBADI dari:</div>
        <table class="table w-100 mb-5">
            <tr>
                <th class="text-left w-30">Nama Usaha</th>
                <td class="border">{{ $data->company_name }} -- {{ $data->member->code }} | {{ $data->member_name }}</td>
            </tr>
        </table>
        <div class="mb-5">Melakukan peminjaman tabung dengan rincian sebagai berikut:</div>
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
        <div class="mb-5">Menyatakan telah membaca serta menyetujui syarat & ketentuan peminjaman tabung sebagai berikut:</div>
        <ol class="mb-5">
            <li>Peminjam tabung telah menjadi member terdaftar Dunia Motor</li>
            <li>Standar isi tabung oksigen: 150 bar / tabung</li>
            <li>Harga sewaktu-waktu bisa berubah</li>
            <li>Jika pembelian isi tabung tidak sesuai minimal pembelian yang telah ditentukan, maka Dunia Motor berhak melakukan penarikan tabung</li>
            <li>Kerusakan & kehilangan tutup tabung & klep tabung masing-masing akan dikenakan biaya dan ditanggung oleh peminjam tabung</li>
            <li>Apabila tabung kemasukan air maka peminjam bersedia mengganti tabung sesuai harga tabung di Dunia Motor</li>
            <li>Kehilangan tabung akan diganti oleh peminjam sesuai harga tabung di Dunia Motor</li>
            <li>Tabung yang dipinjam adalah hak milik Dunia Motor dan pengisian harus dilakukan di Dunia Motor</li>
            <li>Uang jaminan akan dikembalikan full dengan catatan kondisi tabung dalam keadaan baik dan lengkap</li>
            <li>Pengembalian uang jaminan hanya bisa diproses via transfer ke rekening atas "nama usaha" atau "nama peminjam" disurat in</li>
        </ol>
        <div class="mb-5">Demikian surat jaminan ini dibuat dengan sesungguhnya dan sebenar-benarnya.</div>
        <table class="w-100">
            <tr>
                <td class="w-50"><i>Lembar 1 & 3: arsip DM, Lembar 2: customer.</i></td>
                <td class="w-25 text-center">Peminjam,</td>
                <td class="w-25 text-center">Dunia Motor,</td>
            </tr>
            <tr>
                <td class="w-50 h-60px">
                    <i>Harap membawa surat jaminan ketika mengembalikan tabung.</i>
                </td>
                <td class="w-25"></td>
                <td class="w-25"></td>
            </tr>
            <tr>
                <td class="w-50"></td>
                <td class="w-25 bold text-center"><hr>{{ $data->member_name }}</td>
                <td class="w-25 bold text-center"><hr>{{ $data->pic }}</td>
            </tr>
        </table>
    </div>
</body>
</html>