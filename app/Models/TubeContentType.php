<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TubeContentType extends Model
{
    use UuidGenerator;

    public function tubeTransactions(): HasMany
    {
        return $this->hasMany(TubeTransaction::class);
    }
}
