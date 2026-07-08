<?php

namespace App\Constants;

use Illuminate\Support\Collection;

class Permission {
    protected array $permissions = [
        ['name' => 'Semua Akses', 'key' => 'manage-all'],

        ['scope' => 'Supplier'],
        ['name' => 'Lihat Supplier', 'key' => 'view-supplier'],
        ['name' => 'Tambah Supplier', 'key' => 'create-supplier'],
        ['name' => 'Ubah Supplier', 'key' => 'update-supplier'],
        ['name' => 'Hapus Supplier', 'key' => 'delete-supplier'],

        ['scode' => 'Jenis Isi Tabung'],
        ['name' => 'Lihat Jenis Isi Tabung', 'key' => 'view-tube-content-type'],
        ['name' => 'Tambah Jenis Isi Tabung', 'key' => 'create-tube-content-type'],
        ['name' => 'Ubah Jenis Isi Tabung', 'key' => 'update-tube-content-type'],
        ['name' => 'Hapus Jenis Isi Tabung', 'key' => 'delete-tube-content-type'],

        ['scode' => 'Member'],
        ['name' => 'Lihat Member', 'key' => 'view-member'],
        ['name' => 'Tambah Member', 'key' => 'create-member'],
        ['name' => 'Ubah Member', 'key' => 'update-member'],
        ['name' => 'Hapus Member', 'key' => 'delete-member'],

        ['scode' => 'Tabung'],
        ['name' => 'Lihat Tabung', 'key' => 'view-tube'],
        ['name' => 'Tambah Tabung', 'key' => 'create-tube'],
        ['name' => 'Ubah Tabung', 'key' => 'update-tube'],
        ['name' => 'Hapus Tabung', 'key' => 'delete-tube'],

        ['scode' => 'Barcode Tabung'],
        ['name' => 'Lihat Barcode Tabung', 'key' => 'view-tube-barcode'],
        ['name' => 'Ubah Barcode Tabung', 'key' => 'update-tube-barcode'],

        ['scode' => 'Transaksi'],
        ['name' => 'Lihat Transaksi', 'key' => 'view-transaction'],
        ['name' => 'Tambah Transaksi', 'key' => 'create-transaction'],
        ['name' => 'Scan Transaksi', 'key' => 'create-items-transaction'],
        ['name' => 'Hapus Transaksi', 'key' => 'delete-transaction'],
        ['name' => 'Hapus Item Transaksi', 'key' => 'delete-transaction-item'],
    ];

    public function list(): Collection
    {
        return collect($this->permissions)->values();
    }

    public function getName(string $access): string | null
    {
        return collect($this->permissions)->where('key', $access)->value('name');
    }

    public function exist(string $access): bool
    {
        return collect($this->permissions)->where('key', $access)->count() ? true : false;
    }
}