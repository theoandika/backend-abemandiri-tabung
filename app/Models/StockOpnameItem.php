<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[WithoutTimestamps]
class StockOpnameItem extends Model
{
    use UuidGenerator;

    protected function casts(): array{
        return [
            'match' => 'boolean',
            'adjust' => 'boolean'
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function ($item) {
            if ($item->adjust) {
                $item->tubeTransaction->delete();
            }
            if ($item->photo) {
                $item->photo->delete();
            }
        });
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }

    public function tubeTransaction(): BelongsTo
    {
        return $this->belongsTo(TubeTransaction::class);
    }

    public function photo(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
