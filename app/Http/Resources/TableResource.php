<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'table_number' => $this->table_number,
            'capacity'     => $this->capacity,
            'location'     => $this->location,
            'is_active'    => $this->is_active,
            'notes'        => $this->notes,
            'created_at'   => $this->created_at->toIso8601String(),
        ];
    }
}
