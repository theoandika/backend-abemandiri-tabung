<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameTubeListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'barcode' => $this->barcode,
            'site' => new SimpleSiteResource($this->site),
            'position' => $this->position,
            'tube_status' => $this->status,
            'own' => $this->own,
            'second_owner' => new SimpleMemberResource($this->second_owner),
        ];
    }
}
