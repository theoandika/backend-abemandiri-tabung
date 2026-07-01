<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailTubeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'number' => $this->number,
            'barcode' => $this->barcode,
            'tube_content' => new DetailTubeContentTypeResource($this->latestTubeContent->tubeContentType),
            'type' => $this->type,
            'own' => $this->own,
            'active' => $this->active,
            'photo' => $this->latestTubeBarcode?->photo?->url ?? null,
        ];
    }
}
