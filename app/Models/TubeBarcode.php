<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TubeBarcode extends Model
{
    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }
}
