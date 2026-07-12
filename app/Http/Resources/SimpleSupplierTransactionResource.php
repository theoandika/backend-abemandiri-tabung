<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleSupplierTransactionResource extends JsonResource
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
            'site' => new SimpleSiteResource($this->site),
            'supplier' => new SimpleSupplierResource($this->supplier),
            'date' => $this->date,
            'transaction_type' => $this->transaction_type,
            'tube_status' => $this->tube_status,
            'item_count' => $this->supplierTransactionItems()->count()
        ];
    }
}
