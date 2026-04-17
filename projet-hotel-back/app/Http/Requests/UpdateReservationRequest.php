<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La Policy du contrôleur gère l'autorisation
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'sometimes' signifie que le champ n'est pas obligatoire dans la requête,
            // mais s'il est présent, il doit respecter les règles qui suivent.
            'date_arrive' => 'sometimes|required|date|after_or_equal:today',
            'date_depart' => 'sometimes|required|date|after:date_arrive',
        ];
    }
}
