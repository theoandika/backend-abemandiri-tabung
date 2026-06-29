<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TubeContent extends Model
{
    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }

    public function tubeContentType(): BelongsTo
    {
        return $this->belongsTo(TubeContentType::class);
    }
}
