<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Member extends Model
{
    use UuidGenerator;

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function tubeTransactions(): MorphMany
    {
        return $this->morphMany(TubeTransaction::class, 'locationable');
    }
}
