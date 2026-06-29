<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use UuidGenerator;

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
