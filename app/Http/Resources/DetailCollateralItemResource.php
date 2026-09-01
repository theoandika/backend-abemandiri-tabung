<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailCollateralItemResource extends JsonResource
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
            'tube_content_type' => new DetailTubeContentTypeResource($this->tubeContentType),
            'klep_condition' => $this->klep_condition,
            'tube_cap' => $this->tube_cap,
            'tube_quantity' => $this->tube_quantity,
            'nominal' => $this->nominal,
            'total_amount' => $this->totalAmount,
        ];
    }
}
