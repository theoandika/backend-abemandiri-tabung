<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameReportResource extends JsonResource
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
            'date' => $this->stockOpname->created_at,
            'site' => new SimpleSiteResource($this->stockOpname->site),
            'number' => $this->tube->number,
            'barcode' => $this->tubeTransaction->barcode,
            'tube_content' => new DetailTubeContentTypeResource($this->tubeTransaction->tube_content_type),
            'type' => $this->tube->type,
            'tube_owner' => $this->tubeTransaction->tube_owner,
            'status' => $this->tubeTransaction->tube_status,
            'position' => $this->tubeTransaction->position,
            'position_name' => $this->tubeTransaction->position_name,
            'match' => $this->match,
            'adjust' => $this->adjust
        ];
    }
}
