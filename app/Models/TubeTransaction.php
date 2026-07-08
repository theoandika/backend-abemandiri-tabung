<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TubeTransaction extends Model
{
    use UuidGenerator;

    public function tubeContentType(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastContentType = TubeContent::where('tube_id', $this->tube_id)
                    ->where('created_at', '<=', $attr['date'])
                    ->orderByDesc('created_at')
                    ->first();
                if ($lastContentType) {
                    return $lastContentType->tubeContentType;
                } else {
                    $lastContentType = TubeContent::where('tube_id', $this->tube_id)
                        ->orderBy('created_at')
                        ->first();
                    return $lastContentType->tubeContentType;
                }
            }
        );
    }

    public function isPast(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $newTransaction = TubeTransaction::where('tube_id', $attr['tube_id'])->where('date', '>', $attr['date'])->exists();
                if ($newTransaction) {
                    return true;
                }
                return false;
            }
        );
    }

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
