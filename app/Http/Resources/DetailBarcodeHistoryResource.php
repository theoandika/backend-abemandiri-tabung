<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailBarcodeHistoryResource extends JsonResource
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
            'barcodes' => DetailTubeBarcodeResource::collection($this->tubeBarcodes()->orderBy('created_at', 'desc')->get()),
        ];
    }
}
