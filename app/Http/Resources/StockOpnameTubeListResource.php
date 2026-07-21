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
            'tube' => new DetailTubeResource($this),
            'latestTransaction' => new DetailTubeTransactionResource($this->latestTubeTransaction)
        ];
    }
}
