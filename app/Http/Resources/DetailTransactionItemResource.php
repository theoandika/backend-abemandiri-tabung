<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailTransactionItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->tube->uid,
            'number' => $this->tube->number,
            'barcode' => $this->tube->barcode,
            'tube_content_type' => new DetailTubeContentTypeResource($this->tubeTransaction->tubeContentType),
            'tube_owner' => $this->tube->own ? ($this->tube->is_sold ? 'Non DM' : 'DM') : 'Non DM',
        ];
    }
}
