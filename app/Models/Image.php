<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use UuidGenerator;

    protected static function booted(): void
    {
        static::updating(function ($image) {
            if ($image->isDirty('path')) {
                if (Storage::disk('images')->exists($image->getOriginal('path'))) {
                    Storage::disk('images')->delete($image->getOriginal('path'));
                }
            }
        });

        static::deleting(function ($image) {
            if (Storage::disk('images')->exists($image->getOriginal('path'))) {
                Storage::disk('images')->delete($image->getOriginal('path'));
            }
        });
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $disk = Storage::disk('images');
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                return $disk->url($attr['path']);
            }
        );
    }

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
