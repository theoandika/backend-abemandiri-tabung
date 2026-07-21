<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    use UuidGenerator;

    protected function notMatchCount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attr) => StockOpnameItem::where('stock_opname_id', $attr['id'])->where('match', false)->count()
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
