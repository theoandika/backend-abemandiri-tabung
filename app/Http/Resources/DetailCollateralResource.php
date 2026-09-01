<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailCollateralResource extends JsonResource
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
            'member' => new SimpleMemberResource($this->member),
            'type' => $this->type,
            'pic' => $this->pic,
            'document_number' => $this->document_number,
            'member_name' => $this->member_name,
            'member_address' => $this->member_address,
            'signatory_status' => $this->signatory_status,
            'company_name' => $this->company_name,
            'contact_person' => $this->contact_person,
            'payment_method' => $this->payment_method,
            'payment_date' => $this->payment_date,
            'return_payment_method' => $this->return_payment_method,
            'return_payment_date' => $this->return_payment_date,
            'collateral_audit' => $this->collateral_audit,
            'return_audit' => $this->return_audit,
            'document' => $this->document?->url,
            'collateral_items' => DetailCollateralItemResource::collection($this->collateralItems),
            'total_nominal' => $this->total_nominal,
            'generated_document' => $this->generatedDocument,
        ];
    }
}
