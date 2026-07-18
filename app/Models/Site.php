<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use UuidGenerator;

    public function userSites(): HasMany
    {
        return $this->hasMany(UserSite::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function supplierTransactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    public function tubeTransactions(): HasMany
    {
        return $this->hasMany(TubeTransaction::class);
    }
}
