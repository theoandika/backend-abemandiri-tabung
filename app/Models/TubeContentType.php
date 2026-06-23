<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TubeContentType extends Model
{
    use UuidGenerator;

    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }

    public function tubeContentType(): BelongsTo
    {
        return $this->belongsTo(TubeContentType::class);
    }

    public function locationable(): MorphTo
    {
        return $this->morphTo();
    }
}
