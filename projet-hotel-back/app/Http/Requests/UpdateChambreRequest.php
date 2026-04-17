<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChambreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Géré par la policy dans le contrôleur
    }

    public function rules(): array
    {
        $chambreId = $this->route('chambre')->id;

        return [
            'numero_chambre' => ['sometimes', 'string', 'max:50', Rule::unique('chambres')->ignore($chambreId)],
            'type'           => 'sometimes|string|max:100',
            'prix_nuite'     => 'sometimes|numeric|min:0',
            'disponibilite'  => 'sometimes|boolean',
            'photo'          => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'description'    => 'nullable|string|max:2000'
        ];
    }
}
