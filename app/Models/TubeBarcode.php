<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TubeBarcode extends Model
{
    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }

    public function photo(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
