<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tube extends Model
{
    use UuidGenerator;

    protected static function booted(): void
    {
        static::deleting(function ($tube) {
            foreach ($tube->tubeBarcodes as $tubeBarcode) {
                $tubeBarcode->delete();
            }
        });
    }

    protected function casts(): array{
        return [
            'own' => 'boolean',
            'active' => 'boolean'
        ];
    }

    protected function barcode(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attr) => TubeBarcode::where('tube_id', $attr['id'])->latest()->first()?->barcode ?? null
        );
    }

    protected function tubeContent(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attr) => TubeContent::where('tube_id', $attr['id'])->latest()->first()?->tubeContentType?->name ?? null
        );
    }

    protected function site(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest()->first();
                if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == 'App\Models\Site') {
                    return null;
                }
                return $lastTransaction?->site ?? null;
            }
        );
    }

    protected function position(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest()->first();
                if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == null) {
                    return 'transit';
                } else if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                    return 'member';
                } else if ($lastTransaction?->transaction_type == 'in' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                    return 'site';
                } else if ($lastTransaction?->transaction_type == 'in' && $lastTransaction?->locationable_type == null) {
                    return 'site';
                } else if ($lastTransaction?->transaction_type == 'return' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                    return 'site';
                } else if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                    return 'supplier';
                } else if ($lastTransaction?->transaction_type == 'in' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                    return 'site';
                } else if ($lastTransaction?->transaction_type == 'sell' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                    return 'member';
                } else {
                    return 'unknown';
                }
            }
        );
    }

    protected function isStatusReadyToSellMember(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest()->first();
                if ($lastTransaction?->tube_status == 'broken' || $lastTransaction?->tube_status == 'expired') {
                    return false;
                }
                return true;
            }
        );
    }

    protected function isPositionReadyToOutMember(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                if ($this->position == 'member' || $this->position == 'transit' || $this->position == 'supplier') {
                    return false;
                }
                return true;
            }
        );
    }

    protected function isSold(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->where('transaction_type', 'sell')->first();
                if ($lastTransaction) {
                    return true;
                }
                return false;
            }
        );
    }

    protected function secondOwner(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->where('transaction_type', 'sell')->first();
                if ($lastTransaction) {
                    return $lastTransaction->locationable;
                }
                return null;
            }
        );
    }

    protected function own(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                if ($attr['own']) {
                    return !$this->is_sold;
                } else {
                    return $attr['own'];
                }
            }
        );
    }

    public function tubeBarcodes(): HasMany
    {
        return $this->hasMany(TubeBarcode::class);
    }

    public function latestTubeBarcode(): HasOne
    {
        return $this->hasOne(TubeBarcode::class)->latestOfMany();
    }

    public function tubeContents(): HasMany
    {
        return $this->hasMany(TubeContent::class);
    }

    public function latestTubeContent(): HasOne
    {
        return $this->hasOne(TubeContent::class)->latestOfMany();
    }

    public function tubeTransactions(): HasMany
    {
        return $this->hasMany(TubeTransaction::class);
    }

    public function latestTubeTransaction(): HasOne
    {
        return $this->hasOne(TubeTransaction::class)->latestOfMany('date');
    }
}
