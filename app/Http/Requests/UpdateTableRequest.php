<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locations = implode(',', config('reservation.locations'));

        return [
            'table_number' => ['sometimes', 'integer', 'min:1', 'max:999', 'unique:tables,table_number,' . $this->route('table')->id],
            'capacity'     => ['sometimes', 'integer', 'min:1', 'max:20'],
            'location'     => ['sometimes', 'string', "in:{$locations}"],
            'is_active'    => ['sometimes', 'boolean'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
