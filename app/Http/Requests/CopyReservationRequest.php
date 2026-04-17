<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CopyReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ];
    }
}
