<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locations = implode(',', config('reservation.locations'));

        return [
            'table_number' => ['required', 'integer', 'min:1', 'max:999', 'unique:tables,table_number'],
            'capacity'     => ['required', 'integer', 'min:1', 'max:20'],
            'location'     => ['required', 'string', "in:{$locations}"],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
