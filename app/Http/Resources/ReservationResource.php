<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'reference_code' => $this->reference_code,
            'status'         => $this->status,

            'table' => new TableResource($this->whenLoaded('table')),

            'customer' => [
                'name'    => $this->customer_name,
                'email'   => $this->customer_email,
                'phone'   => $this->customer_phone,
            ],

            'guest_count'      => $this->guest_count,
            'special_requests' => $this->special_requests,

            'slot' => [
                'date'  => $this->reservation_date->format('Y-m-d'),
                'start' => $this->slot_start,
                'end'   => $this->slot_end,
            ],

            'cancellation' => $this->when($this->status === 'cancelled', [
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'reason'       => $this->cancellation_reason,
            ]),

            'can_cancel' => $this->isCancellable(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
