<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Transaction extends Model
{
    use UuidGenerator;

    protected static function booted(): void
    {
        static::deleting(function ($transaction) {
            if ($transaction->document) {
                $transaction->document->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
