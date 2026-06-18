<?php

namespace App\Models;

use App\Traits\UuidGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use UuidGenerator;

    protected static function booted(): void
    {
        static::updating(function ($document) {
            if ($document->isDirty('path')) {
                if (Storage::disk('documents')->exists($document->getOriginal('path'))) {
                    Storage::disk('documents')->delete($document->getOriginal('path'));
                }
            }
        });

        static::deleting(function ($document) {
            if (Storage::disk('documents')->exists($document->getOriginal('path'))) {
                Storage::disk('documents')->delete($document->getOriginal('path'));
            }
        });
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attr) {
                $disk = Storage::disk('documents');
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                return $disk->url($attr['path']);
            }
        );
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
