<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSite extends Model
{
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
