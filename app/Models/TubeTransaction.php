<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TubeTransaction extends Model
{
    use UuidGenerator;

    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function locationable(): MorphTo
    {
        return $this->morphTo();
    }
}
