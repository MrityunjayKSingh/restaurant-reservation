<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxDays   = config('reservation.max_advance_days', 30);
        $locations = implode(',', config('reservation.locations'));
        $maxDate   = now()->addDays($maxDays)->format('Y-m-d');

        return [
            // Either specify a table or let smart assignment handle it
            'table_id'          => ['nullable', 'integer', 'exists:tables,id'],
            'preferred_location' => ['nullable', 'string', "in:{$locations}"],

            // Customer
            'customer_name'    => ['required', 'string', 'max:100'],
            'customer_email'   => ['required', 'email', 'max:150'],
            'customer_phone'   => ['required', 'string', 'max:20'],
            'guest_count'      => ['required', 'integer', 'min:1', 'max:20'],
            'special_requests' => ['nullable', 'string', 'max:500'],

            // Slot
            'reservation_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today', "before_or_equal:{$maxDate}"],
            'slot_start'       => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_date.after_or_equal' => 'Reservation date must be today or in the future.',
            'slot_start.date_format'           => 'Slot start time must be in HH:MM (24h) format.',
        ];
    }
}
