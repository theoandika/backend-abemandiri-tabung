<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
class TransactionItem extends Model
{    
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function tube()
    {
        return $this->belongsTo(Tube::class);
    }

    public function tubeTransaction()
    {
        return $this->belongsTo(TubeTransaction::class);
    }
}
