<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

trait UuidGenerator {
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::orderedUuid();
    }

    public function uniqueIds(): array
    {
        return ['uid'];
    }
}