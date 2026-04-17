<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChambreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_chambre' => 'required|string|max:50|unique:chambres,numero_chambre',
            'type'           => 'required|string|max:100',
            'prix_nuite'     => 'required|numeric|min:0',
            'disponibilite'  => 'sometimes|boolean',
            'photo'          => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'description'    => 'nullable|string|max:2000'
        ];
    }
}
