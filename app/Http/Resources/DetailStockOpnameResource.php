<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailStockOpnameResource extends JsonResource
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
            'date' => $this->date,
            'site' => new SimpleSiteResource($this->site),
            'content' => new DetailTubeContentTypeResource($this->tubeContentType),
            'pic' => $this->pic,
            'tube_status' => $this->tube_status,
            'tube_count' => $this->stockOpnameItems()->count(),
            'not_match_count' => $this->not_match_count,
            'tubes' => StockOpnameItemResource::collection($this->stockOpnameItems)
        ];
    }
}
