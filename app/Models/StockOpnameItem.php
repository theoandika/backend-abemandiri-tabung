<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
class StockOpnameItem extends Model
{
    protected function casts(): array{
        return [
            'match' => 'boolean',
        ];
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
}
