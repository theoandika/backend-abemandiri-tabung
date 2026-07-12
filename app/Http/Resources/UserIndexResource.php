<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserIndexResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => new RoleWithPermissionResource($this->role),
            'sites' => SimpleSiteResource::collection($this->sites),
            'is_active' => $this->is_active
        ];
    }
}
