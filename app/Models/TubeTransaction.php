<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TubeTransaction extends Model
{
    use UuidGenerator;

    public function tubeContentType(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastContentType = TubeContent::where('tube_id', $this->tube_id)
                    ->where('created_at', '<=', $attr['date'])
                    ->orderByDesc('created_at')
                    ->first();
                if ($lastContentType) {
                    return $lastContentType->tubeContentType;
                } else {
                    $lastContentType = TubeContent::where('tube_id', $this->tube_id)
                        ->orderBy('created_at')
                        ->first();
                    return $lastContentType->tubeContentType;
                }
            }
        );
    }

    public function tubeOwner(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $soldTransaction = TubeTransaction::where('tube_id', $attr['id'])->where('transaction_type', 'sell')->first();
                if ($soldTransaction) {
                    if (Carbon::parse($attr['date'])->greaterThanOrEqualTo(Carbon::parse($soldTransaction->date))) {
                        return 'NON DM';
                    } else {
                        return 'Tabung DM';
                    }
                } else {
                    return 'Tabung DM';
                }
            }
        );
    }

    public function position(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                if ($attr['transaction_type'] == 'out' && $attr['locationable_type'] == null) {
                    return 'transit';
                } else if ($attr['transaction_type'] == 'out' && $attr['locationable_type'] == 'App\Models\Member') {
                    return 'member';
                } else if ($attr['transaction_type'] == 'in' && $attr['locationable_type'] == 'App\Models\Member') {
                    return 'site';
                } else if ($attr['transaction_type'] == 'in' && $attr['locationable_type'] == null) {
                    return 'site';
                } else if ($attr['transaction_type'] == 'return' && $attr['locationable_type'] == 'App\Models\Member') {
                    return 'site';
                } else if ($attr['transaction_type'] == 'refill' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    return 'supplier';
                } else if ($attr['transaction_type'] == 'filled' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    return 'site';
                } else if ($attr['transaction_type'] == 'sell' && $attr['locationable_type'] == 'App\Models\Member') {
                    return 'member';
                } else if ($attr['transaction_type'] == 'fixing' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    return 'supplier';
                } else if ($attr['transaction_type'] == 'fixed' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    return 'site';
                } else {
                    return 'unknown';
                }
            }
        );
    }

    protected function positionName(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                if ($attr['transaction_type'] == 'out' && $attr['locationable_type'] == null) {
                    return 'Transit';
                } else if ($attr['transaction_type'] == 'out' && $attr['locationable_type'] == 'App\Models\Member') {
                    $member = Member::where('id', $attr['locationable_id'])->first();
                    return $member->code.' - '.$member->name;
                } else if ($attr['transaction_type'] == 'in' && $attr['locationable_type'] == 'App\Models\Member') {
                    $site = Site::where('id', $attr['site_id'])->first();
                    return $site->name;
                } else if ($attr['transaction_type'] == 'in' && $attr['locationable_type'] == null) {
                    $site = Site::where('id', $attr['site_id'])->first();
                    return $site->name;
                } else if ($attr['transaction_type'] == 'return' && $attr['locationable_type'] == 'App\Models\Member') {
                    $site = Site::where('id', $attr['site_id'])->first();
                    return $site->name;
                } else if ($attr['transaction_type'] == 'refill' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    $supplier = Supplier::where('id', $attr['site_id'])->first();
                    return $supplier->code.' - '.$supplier->name;
                } else if ($attr['transaction_type'] == 'filled' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    $site = Site::where('id', $attr['site_id'])->first();
                    return $site->name;
                } else if ($attr['transaction_type'] == 'sell' && $attr['locationable_type'] == 'App\Models\Member') {
                    $member = Member::where('id', $attr['locationable_id'])->first();
                    return $member->code.' - '.$member->name;
                } else if ($attr['transaction_type'] == 'fixing' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    $supplier = Supplier::where('id', $attr['site_id'])->first();
                    return $supplier->code.' - '.$supplier->name;
                } else if ($attr['transaction_type'] == 'fixed' && $attr['locationable_type'] == 'App\Models\Supplier') {
                    $site = Site::where('id', $attr['site_id'])->first();
                    return $site->name;
                } else {
                    return 'Tidak diketahui';
                }
            }
        );
    }

    public function isPast(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $newTransaction = TubeTransaction::where('tube_id', $attr['tube_id'])->where('date', '>', $attr['date'])->exists();
                if ($newTransaction) {
                    return true;
                }
                return false;
            }
        );
    }

    public function isAdjustByStockOpaname(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attr) => StockOpnameItem::where('tube_transaction_id', $attr['id'])->where('match', false)->exists()
        );
    }

    public function tube(): BelongsTo
    {
        return $this->belongsTo(Tube::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function locationable(): MorphTo
    {
        return $this->morphTo();
    }
}
