<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TubeContentType extends Model
{
    use UuidGenerator;

    public function tubeContents(): HasMany
    {
        return $this->hasMany(TubeContent::class);
    }
}
