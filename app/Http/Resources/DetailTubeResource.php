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
            'site' => new SimpleSiteResource($this->site),
            'number' => $this->number,
            'barcode' => $this->barcode,
            'tube_content' => new DetailTubeContentTypeResource($this->latestTubeContent->tubeContentType),
            'type' => $this->type,
            'own' => $this->own,
            'active' => $this->active,
            'status' => $this->status,
            'position' => $this->position,
            'second_owner' => new SimpleMemberResource($this->second_owner),
            'photo' => $this->latestTubeBarcode?->photo?->url ?? null,
        ];
    }
}
