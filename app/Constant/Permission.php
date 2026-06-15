<?php

namespace App\Constants;

use Illuminate\Support\Collection;

class Permission {
    protected array $permissions = [
        ['name' => 'Semua Akses', 'key' => 'manage-all'],

        ['scope' => 'Omzet Cabang'],
        ['name' => 'Lihat Omzet Cabang', 'key' => 'view-site-revenue'],
        ['name' => 'Tambah Omzet Cabang', 'key' => 'create-site-revenue'],
        ['name' => 'Ubah Omzet Cabang', 'key' => 'update-site-revenue'],
        ['name' => 'Hapus Omzet Cabang', 'key' => 'delete-site-revenue'],
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