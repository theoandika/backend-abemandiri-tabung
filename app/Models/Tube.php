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
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest('date')->first();
                if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == null) {
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
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest('date')->latest('id')->first();
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
                } else if ($lastTransaction?->transaction_type == 'refill' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                    return 'supplier';
                } else if ($lastTransaction?->transaction_type == 'filled' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                    return 'site';
                } else if ($lastTransaction?->transaction_type == 'sell' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                    return 'member';
                } else if ($lastTransaction?->transaction_type == 'fixing' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                    return 'supplier';
                } else if ($lastTransaction?->transaction_type == 'fixed' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                    return 'site';
                } else {
                    return 'unknown';
                }
            }
        );
    }

    protected function isUsable(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest('date')->first();
                if ($lastTransaction?->tube_status == 'broken' || $lastTransaction?->tube_status == 'expired') {
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

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest('date')->first();
                return $lastTransaction?->tube_status ?? "unknown";
            }
        );
    }

    protected function statusNaming(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $lastTransaction = TubeTransaction::where('tube_id', $attr['id'])->latest('date')->latest('id')->first();
                if (!$this->own) {
                    if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == null) {
                        return 'TRANSIT';
                    } else if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        return 'DI MEMBER';
                    } else if ($lastTransaction?->transaction_type == 'in' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        return 'DI CABANG';
                    } else if ($lastTransaction?->transaction_type == 'in' && $lastTransaction?->locationable_type == null) {
                        return 'DI CABANG';
                    } else if ($lastTransaction?->transaction_type == 'return' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        return 'DI CABANG';
                    } else if ($lastTransaction?->transaction_type == 'refill' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        return 'PENGISIAN KE SUPPLIER';
                    } else if ($lastTransaction?->transaction_type == 'filled' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        return 'DI CABANG';
                    } else if ($lastTransaction?->transaction_type == 'sell' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        return 'DI MEMBER';
                    } else if ($lastTransaction?->transaction_type == 'fixing' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        return 'PERBAIKAN KE SUPPLIER';
                    } else if ($lastTransaction?->transaction_type == 'fixed' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        return 'DI CABANG';
                    } else {
                        return 'TIDAK DIKETAHUI';
                    }
                } else {
                    if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == null) {
                        return 'TRANSIT';
                    } else if ($lastTransaction?->transaction_type == 'out' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        return 'DI PINJAM';
                    } else if ($lastTransaction?->transaction_type == 'in' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        switch ($lastTransaction?->tube_status) {
                            case 'empty':
                                return 'STOK TOKO KOSONG';
                            case 'filled':
                                return 'STOK TOKO ISI';
                            case 'broken':
                                return 'STOK TOKO BERMASALAH';
                            case 'expired':
                                return 'STOK TOKO AFKIR';
                            case 'display':
                                return 'STOK TOKO PAJANGAN';
                            default:
                                return 'DI CABANG';
                        }
                    } else if ($lastTransaction?->transaction_type == 'in' && $lastTransaction?->locationable_type == null) {
                        switch ($lastTransaction?->tube_status) {
                            case 'empty':
                                return 'STOK TOKO KOSONG';
                            case 'filled':
                                return 'STOK TOKO ISI';
                            case 'broken':
                                return 'STOK TOKO BERMASALAH';
                            case 'expired':
                                return 'STOK TOKO AFKIR';
                            case 'display':
                                return 'STOK TOKO PAJANGAN';
                            default:
                                return 'DI CABANG';
                        }
                    } else if ($lastTransaction?->transaction_type == 'return' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        switch ($lastTransaction?->tube_status) {
                            case 'empty':
                                return 'STOK TOKO KOSONG';
                            case 'filled':
                                return 'STOK TOKO ISI';
                            case 'broken':
                                return 'STOK TOKO BERMASALAH';
                            case 'expired':
                                return 'STOK TOKO AFKIR';
                            case 'display':
                                return 'STOK TOKO PAJANGAN';
                            default:
                                return 'DI CABANG';
                        }
                    } else if ($lastTransaction?->transaction_type == 'refill' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        return 'PENGISIAN KE SUPPLIER';
                    } else if ($lastTransaction?->transaction_type == 'filled' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        switch ($lastTransaction?->tube_status) {
                            case 'empty':
                                return 'STOK TOKO KOSONG';
                            case 'filled':
                                return 'STOK TOKO ISI';
                            case 'broken':
                                return 'STOK TOKO BERMASALAH';
                            case 'expired':
                                return 'STOK TOKO AFKIR';
                            case 'display':
                                return 'STOK TOKO PAJANGAN';
                            default:
                                return 'DI CABANG';
                        }
                    } else if ($lastTransaction?->transaction_type == 'sell' && $lastTransaction?->locationable_type == 'App\Models\Member') {
                        return 'DI MEMBER';
                    } else if ($lastTransaction?->transaction_type == 'fixing' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        return 'PERBAIKAN KE SUPPLIER';
                    } else if ($lastTransaction?->transaction_type == 'fixed' && $lastTransaction?->locationable_type == 'App\Models\Supplier') {
                        switch ($lastTransaction?->tube_status) {
                            case 'empty':
                                return 'STOK TOKO KOSONG';
                            case 'filled':
                                return 'STOK TOKO ISI';
                            case 'broken':
                                return 'STOK TOKO BERMASALAH';
                            case 'expired':
                                return 'STOK TOKO AFKIR';
                            case 'display':
                                return 'STOK TOKO PAJANGAN';
                            default:
                                return 'DI CABANG';
                        }
                    } else {
                        return 'TIDAK DIKETAHUI';
                    }
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
        return $this->hasOne(TubeTransaction::class)->ofMany(['date' => 'max', 'id' => 'max']);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function supplierTransactionItems(): HasMany
    {
        return $this->hasMany(SupplierTransactionItem::class);
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
