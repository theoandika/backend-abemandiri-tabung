<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TubeBarcode extends Model
{
    use UuidGenerator;
    
    protected static function booted(): void
    {
        static::deleting(function ($tubeBarcode) {
            if ($tubeBarcode->photo) {
                $tubeBarcode->photo->delete();
            }
        });
    }

    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }

    public function photo(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
