<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierTransaction extends Model
{
    use UuidGenerator;
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function SupplierTransactionItems(): HasMany
    {
        return $this->hasMany(SupplierTransactionItem::class);
    }
}
