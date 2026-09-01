<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
class CollateralItem extends Model
{
    use UuidGenerator;

    public function collateral()
    {
        return $this->belongsTo(Collateral::class);
    }

    public function tubeContentType()
    {
        return $this->belongsTo(TubeContentType::class);
    }

    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => (int) $attributes['tube_quantity'] * $attributes['nominal'],
        );
    }
}
