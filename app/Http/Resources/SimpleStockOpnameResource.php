<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleStockOpnameResource extends JsonResource
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
            'date' => $this->created_at,
            'site' => new SimpleSiteResource($this->site),
            'tube_count' => $this->stockOpnameItems()->count(),
            'not_match_count' => $this->not_match_count
        ];
    }
}
