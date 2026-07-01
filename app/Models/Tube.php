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
}
