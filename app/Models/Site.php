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
}
