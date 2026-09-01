<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Collateral extends Model
{
    use UuidGenerator;

    protected static function booted(): void
    {
        static::deleting(function ($collateral) {
            if ($collateral->document) {
                $collateral->document->delete();
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function collateralItems(): HasMany
    {
        return $this->hasMany(CollateralItem::class);
    }

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    protected function totalNominal(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attr) => (int)CollateralItem::where('collateral_id', $attr['id'])->selectRaw('SUM(tube_quantity * nominal) as total')->value('total')
        );
    }

    protected function generatedDocument(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attr) => $attr['type'] == 'collateral' ? route('collateral.view-document', ['uid' => $attr['uid']]) : route('collateral.view-return-document', ['uid' => $attr['uid']])
        );
    }
}
