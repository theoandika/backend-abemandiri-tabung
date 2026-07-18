<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Supplier extends Model
{
    use UuidGenerator;

    public function supplierTransactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    public function tubeTransactions(): MorphMany
    {
        return $this->morphMany(TubeTransaction::class, 'locationable');
    }
}
