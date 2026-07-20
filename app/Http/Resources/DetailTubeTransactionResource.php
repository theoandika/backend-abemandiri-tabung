<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailTubeTransactionResource extends JsonResource
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
            'number' => $this->tube->number,
            'barcode' => $this->tube->barcode,
            'content' => new DetailTubeContentTypeResource($this->tube_content_type),
            'transaction_type' => $this->transaction_type,
            'tube_status' => $this->tube_status,
            'position' => $this->position,
            'position_name' => $this->position_name
        ];
    }
}
