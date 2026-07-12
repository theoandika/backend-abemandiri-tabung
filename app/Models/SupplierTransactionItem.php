<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps()]
class SupplierTransactionItem extends Model
{
    public function supplierTransaction(): BelongsTo
    {
        return $this->belongsTo(SupplierTransaction::class);
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
