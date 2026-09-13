<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LastTubePositionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uid,
            'site' => new SimpleSiteResource($this->site),
            'number' => $this->number,
            'barcode' => $this->barcode,
            'tube_content' => new DetailTubeContentTypeResource($this->latestTubeContent->tubeContentType),
            'type' => $this->type,
            'own' => $this->own,
            'position' => $this->latestTubeTransaction?->position,
            'position_name' => $this->latestTubeTransaction?->position_name,
            'second_owner' => new SimpleMemberResource($this->second_owner),
            'last_transaction_date' => $this->latestTubeTransaction?->date,
            'status_naming' => $this->statusNaming
        ];
    }
}
