<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
class UserSite extends Model
{
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
