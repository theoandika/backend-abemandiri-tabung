<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameItemResource extends JsonResource
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
            'tube' => new SimpleTubeResource($this->tube),
            'tube_transaction' => new DetailTubeTransactionResource($this->tubeTransaction),
            'match' => $this->match,
            'adjust' => $this->adjust,
            'note' => $this->note,
            'photo' => $this->photo->url
        ];
    }
}
