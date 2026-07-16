<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailTubeWithTubeBarcodeResource extends JsonResource
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
            'number' => $this->number,
            'barcode' => $this->barcode,
            'tube_content' => new DetailTubeContentTypeResource($this->latestTubeContent->tubeContentType),
            'type' => $this->type,
            'tube_barcodes' => DetailTubeBarcodeResource::collection($this->tubeBarcodes()->orderBy('created_at', 'desc')->get()),
            'site' => new SimpleSiteResource($this->site),
        ];
    }
}
